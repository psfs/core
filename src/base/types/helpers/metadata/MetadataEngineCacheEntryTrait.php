<?php

namespace PSFS\base\types\helpers\metadata;

/**
 * Cache tier selection, entry validation, envelopes, and local cache writes.
 *
 * Requires MetadataEngine cache-backend and configuration traits plus local cache and stats state.
 */
trait MetadataEngineCacheEntryTrait
{
    private function readEntry(string $cacheKey, string $signature, int $now): array
    {
        $entry = $this->entryReader()->read($cacheKey, $signature);
        if (is_array($entry)) {
            return $entry;
        }

        self::$stats['metadata.miss']++;
        return [
            'payload' => null,
            'signature' => $signature,
            'soft_expires_at' => $now,
            'hard_expires_at' => $now,
            'created_at' => $now,
        ];
    }

    private function entryReader(): MetadataEntryReader
    {
        return new MetadataEntryReader(
            fn (string $cacheKey, string $signature): ?array => $this->localEntry($cacheKey, $signature),
            fn (string $cacheKey, string $signature): ?array => $this->opcacheEntry($cacheKey, $signature),
            fn (string $cacheKey, string $signature): ?array => $this->redisEntry($cacheKey, $signature)
        );
    }

    private function localEntry(string $cacheKey, string $signature): ?array
    {
        $local = self::$localCache[$cacheKey] ?? null;
        if (!$this->localCacheEnabled() || !is_array($local)) {
            return null;
        }
        if (($local['signature'] ?? null) === $signature) {
            self::$stats['metadata.hit_l0']++;
            return $local;
        }
        unset(self::$localCache[$cacheKey]);
        return null;
    }

    private function opcacheEntry(string $cacheKey, string $signature): ?array
    {
        $entry = $this->readFromOpcacheArtifact($cacheKey);
        if (!is_array($entry)) {
            return null;
        }
        if (($entry['signature'] ?? null) !== $signature) {
            $this->dropOpcacheArtifact($cacheKey);
            return null;
        }
        self::$stats['metadata.hit_l1']++;
        $this->storeLocal($cacheKey, $entry);
        return $entry;
    }

    private function redisEntry(string $cacheKey, string $signature): ?array
    {
        $entry = $this->readFromRedis($cacheKey);
        if (!is_array($entry)) {
            return null;
        }
        if (($entry['signature'] ?? null) !== $signature) {
            $this->dropRedisEntry($cacheKey);
            return null;
        }
        self::$stats['metadata.hit_l2']++;
        $this->storeLocal($cacheKey, $entry);
        $this->writeOpcacheArtifact($cacheKey, $entry);
        return $entry;
    }

    private function buildEntryEnvelope(array $payload, string $signature, int $now): array
    {
        $softTtl = $this->effectiveSoftTtl();
        $hardTtl = $this->effectiveHardTtl();
        return [
            'payload' => $payload,
            'signature' => $signature,
            'soft_expires_at' => $now + $softTtl,
            'hard_expires_at' => $now + $hardTtl,
            'created_at' => $now,
        ];
    }

    protected function writeEntry(string $cacheKey, array $entry): void
    {
        $this->storeLocal($cacheKey, $entry);
        $this->writeOpcacheArtifact($cacheKey, $entry);
        $this->writeToRedis($cacheKey, $entry);
    }

    private function storeLocal(string $cacheKey, array $entry): void
    {
        if (!$this->localCacheEnabled()) {
            return;
        }
        if (count(self::$localCache) >= self::LOCAL_MAX_ENTRIES) {
            array_shift(self::$localCache);
        }
        self::$localCache[$cacheKey] = $entry;
    }

}
