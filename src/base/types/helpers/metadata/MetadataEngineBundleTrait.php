<?php

namespace PSFS\base\types\helpers\metadata;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Class metadata bundle construction, freshness checks, and source signatures.
 *
 * Requires MetadataEngine cache, configuration, and regeneration traits.
 */
trait MetadataEngineBundleTrait
{
    private function getClassBundle(string $fqcn): array
    {
        $className = ltrim($fqcn, '\\');
        $cacheVersion = $this->engineVersion();
        $cacheKey = sha1('class_bundle:' . $className . ':' . $cacheVersion);
        $now = time();

        $fastPayload = $this->fastClassBundlePayload($cacheKey, $className, $now);
        if (is_array($fastPayload)) {
            return $fastPayload;
        }

        if (!class_exists($className)) {
            return $this->emptyClassBundle();
        }
        $reflection = new ReflectionClass($className);
        if (!$this->engineEnabled()) {
            return $this->buildClassBundle($reflection);
        }
        $signature = $this->sourceSignature($reflection);
        $entry = $this->readEntry($cacheKey, $signature, $now);
        $cachedPayload = $this->classBundlePayload($entry, $cacheKey, $className, $now);
        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        return $this->regenerateClassBundle($reflection, $entry, $cacheKey, $signature, $now);
    }

    private function fastClassBundlePayload(string $cacheKey, string $className, int $now): ?array
    {
        if (!$this->engineEnabled() || !$this->localCacheEnabled() || $this->debugEnabled()) {
            return null;
        }

        $fast = $this->readLocalWithoutSignature($cacheKey, $className, $now);
        return is_array($fast['payload'] ?? null) ? $fast['payload'] : null;
    }

    private function emptyClassBundle(): array
    {
        return ['class_tags' => [], 'method_tags' => [], 'property_nodes' => [], 'signature' => ''];
    }

    private function classBundlePayload(array $entry, string $cacheKey, string $className, int $now): ?array
    {
        if (!is_array($entry['payload'] ?? null)) {
            return null;
        }
        if ($this->debugEnabled() || $now <= (int)($entry['soft_expires_at'] ?? 0)) {
            return $entry['payload'];
        }
        if ($now > (int)($entry['hard_expires_at'] ?? 0)) {
            return null;
        }
        if ($this->swrEnabled()) {
            $this->queueBackgroundRegeneration($cacheKey, $className);
        }
        return $entry['payload'];
    }

    private function regenerateClassBundle(
        ReflectionClass $reflection,
        array $entry,
        string $cacheKey,
        string $signature,
        int $now
    ): array {
        $lockAcquired = $this->acquireLock($cacheKey);
        if (!$lockAcquired && is_array($entry['payload'] ?? null) && !$this->debugEnabled()) {
            self::$stats['metadata.lock_contention']++;
            return $entry['payload'];
        }

        try {
            return $this->freshClassBundle($reflection, $cacheKey, $signature, $now);
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($cacheKey);
            }
        }
    }

    private function freshClassBundle(ReflectionClass $reflection, string $cacheKey, string $signature, int $now): array
    {
        $start = hrtime(true);
        $payload = $this->buildClassBundle($reflection);
        self::$stats['metadata.parse_ms'] += (hrtime(true) - $start) / 1000000;
        self::$stats['metadata.regen']++;
        self::$stats['metadata.payload_bytes'] += strlen((string)json_encode($payload));
        $this->writeEntry($cacheKey, $this->buildEntryEnvelope($payload, $signature, $now));

        return $payload;
    }

    protected function buildClassBundle(ReflectionClass $reflection): array
    {
        return $this->attributeBundleBuilder()->build($reflection, $this->sourceSignature($reflection));
    }

    protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
    {
        $class = $reflector instanceof ReflectionClass ? $reflector : $reflector->getDeclaringClass();
        $file = $class->getFileName();
        if (!is_string($file) || !file_exists($file)) {
            return 'class:' . sha1($class->getName());
        }
        clearstatcache(true, $file);
        $mtime = (string)@filemtime($file);
        $size = (string)@filesize($file);
        if ($this->debugEnabled()) {
            $hash = sha1_file($file) ?: '';
            return implode(':', [$mtime, $size, $hash]);
        }
        return implode(':', [$mtime, $size]);
    }

    private function readLocalWithoutSignature(string $cacheKey, string $className, int $now): ?array
    {
        if (!$this->localCacheEnabled()) {
            return null;
        }
        $entry = self::$localCache[$cacheKey] ?? null;
        if (!is_array($entry) || !is_array($entry['payload'] ?? null)) {
            return null;
        }

        $softExpiresAt = (int)($entry['soft_expires_at'] ?? 0);
        $hardExpiresAt = (int)($entry['hard_expires_at'] ?? 0);
        if ($now <= $softExpiresAt) {
            self::$stats['metadata.hit_l0']++;
            return $entry;
        }
        if ($now <= $hardExpiresAt && $this->swrEnabled()) {
            self::$stats['metadata.hit_l0']++;
            $this->queueBackgroundRegeneration($cacheKey, $className);
            return $entry;
        }
        return null;
    }

}
