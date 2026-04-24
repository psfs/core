<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\config\Config;
use PSFS\base\exception\MetadataContractException;
use PSFS\base\Logger;
use PSFS\base\types\helpers\CacheModeHelper;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\MetadataDocParser;
use PSFS\base\types\helpers\attributes\MetadataAttributeContract;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionType;

class MetadataEngine implements MetadataEngineInterface
{
    private const REDIS_PREFIX = 'psfs:metadata:v3:';
    private const LOCK_PREFIX = 'psfs:metadata:v3:lock:';
    private const LOCAL_MAX_ENTRIES = 4096;

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $localCache = [];

    /**
     * @var array<int, array{0:string,1:string}>
     */
    private static array $regenQueue = [];

    /**
     * @var array<string, int|float>
     */
    private static array $stats = [
        'metadata.hit_l0' => 0,
        'metadata.hit_l1' => 0,
        'metadata.hit_l2' => 0,
        'metadata.miss' => 0,
        'metadata.regen' => 0,
        'metadata.lock_contention' => 0,
        'metadata.parse_ms' => 0.0,
        'metadata.payload_bytes' => 0,
    ];

    /**
     * @var array<string, bool>
     */
    private static array $legacyFallbackLogs = [];

    private ?\Redis $redis = null;
    private bool $redisReady = false;
    private bool $shutdownRegistered = false;
    private static bool $debugOpcacheWarningLogged = false;

    public function getTagValue(
        string $tag,
        ?string $doc = '',
        mixed $default = null,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        $doc = $doc ?? '';
        if ($this->attributesEnabled()) {
            $value = $this->readFromAttributesBundle($tag, $reflector);
            if (null !== $value) {
                return $value;
            }
            if ($doc !== '' && $this->hasLegacyTag($tag, $doc)) {
                if (!$this->annotationsFallbackEnabled()) {
                    throw $this->legacyFallbackDisabledException($tag, $reflector);
                }
                $this->rememberLegacyFallback('annotation_' . strtolower($tag));
            }
        }
        return $this->readFromDoc($tag, $doc, $default);
    }

    public function hasDeprecated(?ReflectionMethod $method = null, ?string $doc = ''): bool
    {
        $doc = $doc ?? '';
        if ($this->attributesEnabled() && null !== $method) {
            $attr = $this->readFromAttributesBundle('deprecated', $method);
            if (null !== $attr) {
                return (bool)$attr;
            }
            if ($doc !== '' && MetadataDocParser::hasDeprecatedTag($doc) && !$this->annotationsFallbackEnabled()) {
                throw $this->legacyFallbackDisabledException('deprecated', $method);
            }
            if ($doc !== '' && MetadataDocParser::hasDeprecatedTag($doc) && $this->annotationsFallbackEnabled()) {
                $this->rememberLegacyFallback('annotation_deprecated');
            }
        }
        return $this->annotationsFallbackEnabled() && MetadataDocParser::hasDeprecatedTag($doc);
    }

    public function extractPayload(string $defaultNamespace, ?ReflectionMethod $method = null, ?string $doc = ''): string
    {
        $doc = $doc ?? '';
        $value = null;
        if ($this->attributesEnabled() && null !== $method) {
            $value = $this->readFromAttributesBundle('payload', $method);
            if (null === $value && $doc !== '' && MetadataDocParser::hasTag('payload', $doc) && !$this->annotationsFallbackEnabled()) {
                throw $this->legacyFallbackDisabledException('payload', $method);
            }
            if (null === $value && $doc !== '' && MetadataDocParser::hasTag('payload', $doc) && $this->annotationsFallbackEnabled()) {
                $this->rememberLegacyFallback('annotation_payload');
            }
        }
        if ($value === null && $this->annotationsFallbackEnabled()) {
            $value = MetadataDocParser::readTagValue('payload', $doc, null);
        }
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? $defaultNamespace : $value;
    }

    public function extractReturnSpec(?ReflectionMethod $method = null, ?string $doc = ''): ?string
    {
        $doc = $doc ?? '';
        if ($this->attributesEnabled() && null !== $method) {
            $value = $this->readFromAttributesBundle('return', $method);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            $docReturn = MetadataDocParser::readReturnSpec($doc);
            $docHasLegacyReturnDsl = is_string($docReturn) && preg_match('/^.*\(.*\)$/', $docReturn) === 1;
            if ($docHasLegacyReturnDsl && !$this->annotationsFallbackEnabled()) {
                throw $this->legacyFallbackDisabledException('return', $method);
            }
            if ($docHasLegacyReturnDsl && $this->annotationsFallbackEnabled()) {
                $this->rememberLegacyFallback('annotation_return');
            }
        }
        if (!$this->annotationsFallbackEnabled()) {
            return null;
        }
        $docReturn = MetadataDocParser::readReturnSpec($doc);
        return is_string($docReturn) && preg_match('/^.*\(.*\)$/', $docReturn) === 1 ? $docReturn : null;
    }

    public function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string
    {
        $doc = $doc ?? '';
        if ($this->attributesEnabled() && null !== $property) {
            $injectable = $this->resolveInjectableDefinition($property, $doc);
            if (($injectable['source'] ?? null) === 'attribute' && is_string($injectable['class'])) {
                return $injectable['class'];
            }
            $value = $this->readFromAttributesBundle('var', $property);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            $propertyType = $this->extractPropertyType($property->getType());
            if (null !== $propertyType) {
                return $propertyType;
            }
            if ($doc !== '' && MetadataDocParser::hasTag('var', $doc) && !$this->annotationsFallbackEnabled()) {
                throw $this->legacyFallbackDisabledException('var', $property);
            }
            if ($doc !== '' && MetadataDocParser::hasTag('var', $doc) && $this->annotationsFallbackEnabled()) {
                $this->rememberLegacyFallback('annotation_var');
            }
        }
        if (!$this->annotationsFallbackEnabled()) {
            return null;
        }
        $type = MetadataDocParser::readVarType($doc);
        return is_string($type) && trim($type) !== '' ? $type : null;
    }

    public function resolveInjectableDefinition(?ReflectionProperty $property, ?string $doc = ''): array
    {
        $doc = $doc ?? '';
        $definition = [
            'isInjectable' => false,
            'class' => null,
            'singleton' => true,
            'required' => true,
            'source' => null,
        ];
        if (null !== $property && $this->attributesEnabled()) {
            $injectable = $this->readFromAttributesBundle('injectable', $property);
            if (is_array($injectable) && isset($injectable['class'])) {
                return [
                    'isInjectable' => true,
                    'class' => is_string($injectable['class']) ? $injectable['class'] : null,
                    'singleton' => (bool)($injectable['singleton'] ?? true),
                    'required' => (bool)($injectable['required'] ?? true),
                    'source' => 'attribute',
                ];
            }
            if ($doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1 && !$this->annotationsFallbackEnabled()) {
                throw $this->legacyFallbackDisabledException('injectable', $property);
            }
            if ($doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1 && $this->annotationsFallbackEnabled()) {
                $this->rememberLegacyFallback('annotation_injectable');
            }
        }
        if ($this->annotationsFallbackEnabled() && $doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1) {
            $className = MetadataDocParser::readVarType($doc);
            $className = is_string($className) ? $className : '';
            return [
                'isInjectable' => trim($className) !== '',
                'class' => trim($className) !== '' ? $className : null,
                'singleton' => true,
                'required' => true,
                'source' => 'annotation',
            ];
        }
        return $definition;
    }

    public function getClassMetadata(string $fqcn): ClassMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        return new ClassMetadata($fqcn, $bundle['class_tags'] ?? [], $bundle['signature'] ?? '');
    }

    public function getMethodMetadata(string $fqcn, string $method): MethodMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        $methodTags = $bundle['method_tags'][$method] ?? [];
        return new MethodMetadata($fqcn, $method, $methodTags, $bundle['signature'] ?? '');
    }

    public function getPropertyMetadata(string $fqcn, string $property): PropertyMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        $propertyNode = $bundle['property_nodes'][$property] ?? ['tags' => [], 'type' => null];
        return new PropertyMetadata(
            $fqcn,
            $property,
            is_array($propertyNode['tags'] ?? null) ? $propertyNode['tags'] : [],
            is_string($propertyNode['type'] ?? null) ? $propertyNode['type'] : null,
            $bundle['signature'] ?? ''
        );
    }

    public function getStats(): array
    {
        return self::$stats;
    }

    public function clearLocalCache(): void
    {
        self::$localCache = [];
    }

    /**
     * @return array<int, string>
     */
    public function getLegacyFallbackLogs(): array
    {
        return array_keys(self::$legacyFallbackLogs);
    }

    public function clearLegacyFallbackLogs(): void
    {
        self::$legacyFallbackLogs = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getClassBundle(string $fqcn): array
    {
        $className = ltrim($fqcn, '\\');
        $cacheVersion = $this->engineVersion();
        $cacheKey = sha1('class_bundle:' . $className . ':' . $cacheVersion);
        $now = time();

        if ($this->engineEnabled() && $this->localCacheEnabled() && !$this->debugEnabled()) {
            $fast = $this->readLocalWithoutSignature($cacheKey, $className, $now);
            if (is_array($fast['payload'] ?? null)) {
                return $fast['payload'];
            }
        }

        if (!class_exists($className)) {
            return ['class_tags' => [], 'method_tags' => [], 'property_nodes' => [], 'signature' => ''];
        }
        $reflection = new ReflectionClass($className);
        if (!$this->engineEnabled()) {
            return $this->buildClassBundle($reflection);
        }
        $signature = $this->sourceSignature($reflection);
        $entry = $this->readEntry($cacheKey, $signature, $now);

        if (is_array($entry['payload'] ?? null)) {
            if ($this->debugEnabled()) {
                return $entry['payload'];
            }
            $softExpiresAt = (int)($entry['soft_expires_at'] ?? 0);
            $hardExpiresAt = (int)($entry['hard_expires_at'] ?? 0);
            if ($now <= $softExpiresAt) {
                return $entry['payload'];
            }
            if ($now <= $hardExpiresAt) {
                if ($this->swrEnabled()) {
                    $this->queueBackgroundRegeneration($cacheKey, $className);
                }
                return $entry['payload'];
            }
        }

        $lockAcquired = $this->acquireLock($cacheKey);
        if (!$lockAcquired && is_array($entry['payload'] ?? null) && !$this->debugEnabled()) {
            self::$stats['metadata.lock_contention']++;
            return $entry['payload'];
        }

        try {
            $start = hrtime(true);
            $payload = $this->buildClassBundle($reflection);
            $elapsed = (hrtime(true) - $start) / 1000000;
            self::$stats['metadata.parse_ms'] += $elapsed;
            self::$stats['metadata.regen']++;
            self::$stats['metadata.payload_bytes'] += strlen((string)json_encode($payload));
            $fresh = $this->buildEntryEnvelope($payload, $signature, $now);
            $this->writeEntry($cacheKey, $fresh);
            return $payload;
        } finally {
            if ($lockAcquired) {
                $this->releaseLock($cacheKey);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClassBundle(ReflectionClass $reflection): array
    {
        $classTags = $this->extractAttributeTags($reflection);
        $methodTags = [];
        foreach ($reflection->getMethods() as $method) {
            $methodTags[$method->getName()] = $this->extractAttributeTags($method);
        }
        $propertyNodes = [];
        foreach ($reflection->getProperties() as $property) {
            $propertyNodes[$property->getName()] = [
                'tags' => $this->extractAttributeTags($property),
                'type' => $this->extractPropertyType($property->getType()),
            ];
        }
        return [
            'class_tags' => $classTags,
            'method_tags' => $methodTags,
            'property_nodes' => $propertyNodes,
            'signature' => $this->sourceSignature($reflection),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAttributeTags(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): array
    {
        $tags = [];
        foreach ($reflector->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if (!$instance instanceof MetadataAttributeContract) {
                continue;
            }
            $tags[strtolower($instance::tag())] = $instance->resolve();
        }
        return $tags;
    }

    private function readFromAttributesBundle(
        string $tag,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        if (null === $reflector) {
            return null;
        }
        $normalizedTag = strtolower($tag);
        if ($reflector instanceof ReflectionClass) {
            $bundle = $this->getClassBundle($reflector->getName());
            return $bundle['class_tags'][$normalizedTag] ?? null;
        }
        if ($reflector instanceof ReflectionMethod) {
            $bundle = $this->getClassBundle(
                $reflector->getDeclaringClass()->getName(),
            );
            return $bundle['method_tags'][$reflector->getName()][$normalizedTag] ?? null;
        }
        $bundle = $this->getClassBundle(
            $reflector->getDeclaringClass()->getName(),
        );
        $propertyNode = $bundle['property_nodes'][$reflector->getName()] ?? ['tags' => [], 'type' => null];
        $tags = is_array($propertyNode['tags'] ?? null) ? $propertyNode['tags'] : [];
        $type = is_string($propertyNode['type'] ?? null) ? $propertyNode['type'] : null;
        if ($normalizedTag === 'var' && $type !== null) {
            return $tags[$normalizedTag] ?? $type;
        }
        return $tags[$normalizedTag] ?? null;
    }

    private function readFromDoc(string $tag, ?string $doc, mixed $default = null): mixed
    {
        if ($doc === null || $doc === '') {
            return $default;
        }
        return match ($tag) {
            'http' => MetadataDocParser::readHttpMethod($doc, $default),
            'visible' => MetadataDocParser::readVisibilityFlag($doc),
            'cache' => (bool)MetadataDocParser::readTagValue('cache', $doc, $default ?? false),
            default => MetadataDocParser::readTagValue($tag, $doc, $default),
        };
    }

    private function hasLegacyTag(string $tag, string $doc): bool
    {
        $normalizedTag = strtolower($tag);
        return match ($normalizedTag) {
            'http' => MetadataDocParser::hasHttpMethodTag($doc),
            default => MetadataDocParser::hasTag($normalizedTag, $doc),
        };
    }

    private function legacyFallbackDisabledException(
        string $tag,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): MetadataContractException {
        $where = 'unknown';
        if ($reflector instanceof ReflectionMethod) {
            $where = $reflector->getDeclaringClass()->getName() . '::' . $reflector->getName();
        } elseif ($reflector instanceof ReflectionProperty) {
            $where = $reflector->getDeclaringClass()->getName() . '::$' . $reflector->getName();
        } elseif ($reflector instanceof ReflectionClass) {
            $where = $reflector->getName();
        }
        return new MetadataContractException(sprintf(
            '[MetadataContract] Annotation fallback disabled for `%s` at %s',
            $tag,
            $where
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function readEntry(string $cacheKey, string $signature, int $now): array
    {
        $local = self::$localCache[$cacheKey] ?? null;
        if ($this->localCacheEnabled() && is_array($local) && ($local['signature'] ?? null) === $signature) {
            self::$stats['metadata.hit_l0']++;
            return $local;
        }
        if ($this->localCacheEnabled() && is_array($local) && ($local['signature'] ?? null) !== $signature) {
            unset(self::$localCache[$cacheKey]);
        }

        $opcacheEntry = $this->readFromOpcacheArtifact($cacheKey);
        if (is_array($opcacheEntry) && ($opcacheEntry['signature'] ?? null) === $signature) {
            self::$stats['metadata.hit_l1']++;
            $this->storeLocal($cacheKey, $opcacheEntry);
            return $opcacheEntry;
        }
        if (is_array($opcacheEntry) && ($opcacheEntry['signature'] ?? null) !== $signature) {
            $this->dropOpcacheArtifact($cacheKey);
        }

        $redisEntry = $this->readFromRedis($cacheKey);
        if (is_array($redisEntry) && ($redisEntry['signature'] ?? null) === $signature) {
            self::$stats['metadata.hit_l2']++;
            $this->storeLocal($cacheKey, $redisEntry);
            $this->writeOpcacheArtifact($cacheKey, $redisEntry);
            return $redisEntry;
        }
        if (is_array($redisEntry) && ($redisEntry['signature'] ?? null) !== $signature) {
            $this->dropRedisEntry($cacheKey);
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $entry
     */
    private function writeEntry(string $cacheKey, array $entry): void
    {
        $this->storeLocal($cacheKey, $entry);
        $this->writeOpcacheArtifact($cacheKey, $entry);
        $this->writeToRedis($cacheKey, $entry);
    }

    /**
     * @param array<string, mixed> $entry
     */
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

    protected function queueBackgroundRegeneration(string $cacheKey, string $className): void
    {
        if (!$this->swrEnabled()) {
            return;
        }
        if (!$this->acquireLock($cacheKey)) {
            self::$stats['metadata.lock_contention']++;
            return;
        }
        self::$regenQueue[] = [$cacheKey, $className];
        if ($this->shutdownRegistered) {
            return;
        }
        $this->shutdownRegistered = true;
        register_shutdown_function(function (): void {
            while ($pair = array_shift(self::$regenQueue)) {
                [$key, $className] = $pair;
                try {
                    if (!class_exists($className)) {
                        continue;
                    }
                    $reflection = new ReflectionClass($className);
                    $signature = $this->sourceSignature($reflection);
                    $payload = $this->buildClassBundle($reflection);
                    $entry = $this->buildEntryEnvelope($payload, $signature, time());
                    $this->writeEntry($key, $entry);
                    self::$stats['metadata.regen']++;
                } catch (\Throwable $exception) {
                    Logger::log('[MetadataEngine][SWR] ' . $exception->getMessage(), LOG_WARNING);
                } finally {
                    $this->releaseLock($key);
                }
            }
        });
    }

    protected function readFromOpcacheArtifact(string $cacheKey): ?array
    {
        if (!$this->opcacheEnabled()) {
            return null;
        }
        $path = $this->artifactPath($cacheKey);
        if (!file_exists($path)) {
            return null;
        }
        $entry = include $path;
        return is_array($entry) ? $entry : null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function writeOpcacheArtifact(string $cacheKey, array $entry): void
    {
        if (!$this->opcacheEnabled()) {
            return;
        }
        $path = $this->artifactPath($cacheKey);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
        $content = "<?php\nreturn " . var_export($entry, true) . ";\n";
        if (@file_put_contents($path, $content) === false) {
            return;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($path);
        }
    }

    protected function dropOpcacheArtifact(string $cacheKey): void
    {
        $path = $this->artifactPath($cacheKey);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    protected function readFromRedis(string $cacheKey): ?array
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return null;
        }
        try {
            $raw = $redis->get(self::REDIS_PREFIX . $cacheKey);
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Redis] ' . $exception->getMessage(), LOG_WARNING);
            $this->redisReady = false;
            $this->redis = null;
            return null;
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function writeToRedis(string $cacheKey, array $entry): void
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return;
        }
        try {
            $redisKey = self::REDIS_PREFIX . $cacheKey;
            $encoded = json_encode($entry);
            if (!is_string($encoded) || $encoded === '') {
                return;
            }
            $redis->setex($redisKey, $this->effectiveHardTtl(), $encoded);
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Redis] ' . $exception->getMessage(), LOG_WARNING);
            $this->redisReady = false;
            $this->redis = null;
        }
    }

    protected function dropRedisEntry(string $cacheKey): void
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return;
        }
        try {
            $redis->del(self::REDIS_PREFIX . $cacheKey);
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Redis] ' . $exception->getMessage(), LOG_WARNING);
        }
    }

    protected function acquireLock(string $cacheKey): bool
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return true;
        }
        $lockKey = self::LOCK_PREFIX . $cacheKey;
        try {
            $ok = $redis->set($lockKey, (string)getmypid(), ['nx', 'ex' => $this->regenLockTtl()]);
            return $ok === true || $ok === 'OK';
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Lock] ' . $exception->getMessage(), LOG_WARNING);
            return true;
        }
    }

    protected function releaseLock(string $cacheKey): void
    {
        $redis = $this->redisClient();
        if (null === $redis) {
            return;
        }
        try {
            $redis->del(self::LOCK_PREFIX . $cacheKey);
        } catch (\RedisException $exception) {
            Logger::log('[MetadataEngine][Lock] ' . $exception->getMessage(), LOG_WARNING);
        }
    }

    protected function redisClient(): ?\Redis
    {
        if (!$this->redisEnabled()) {
            return null;
        }
        if ($this->redisReady && $this->redis instanceof \Redis) {
            return $this->redis;
        }
        if (!class_exists(\Redis::class)) {
            return null;
        }
        $hosts = array_values(array_filter(array_unique([
            getenv('PSFS_REDIS_HOST') ?: null,
            (string)Config::getParam('redis.host', ''),
            'redis',
            'core-redis-1',
            '127.0.0.1',
        ])));
        $port = (int)(getenv('PSFS_REDIS_PORT') ?: Config::getParam('redis.port', 6379));
        $timeout = (float)(getenv('PSFS_REDIS_TIMEOUT') ?: Config::getParam('redis.timeout', 0.2));
        foreach ($hosts as $host) {
            try {
                $redis = new \Redis();
                if ($redis->connect($host, $port, $timeout)) {
                    $this->redis = $redis;
                    $this->redisReady = true;
                    return $this->redis;
                }
            } catch (\RedisException) {
            }
        }
        return null;
    }

    private function artifactPath(string $cacheKey): string
    {
        return CACHE_DIR
            . DIRECTORY_SEPARATOR
            . 'metadata'
            . DIRECTORY_SEPARATOR
            . $this->engineVersion()
            . DIRECTORY_SEPARATOR
            . substr($cacheKey, 0, 2)
            . DIRECTORY_SEPARATOR
            . $cacheKey . '.php';
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

    private function extractPropertyType(?ReflectionType $type): ?string
    {
        if (null === $type || (method_exists($type, 'isBuiltin') && $type->isBuiltin())) {
            return null;
        }
        $name = method_exists($type, 'getName') ? $type->getName() : null;
        if (!is_string($name) || $name === '') {
            return null;
        }
        return str_starts_with($name, '\\') ? $name : '\\' . $name;
    }

    private function debugEnabled(): bool
    {
        return (bool)Config::getParam('debug', false);
    }

    private function attributesEnabled(): bool
    {
        return (bool)Config::getParam('metadata.attributes.enabled', true);
    }

    private function annotationsFallbackEnabled(): bool
    {
        return (bool)Config::getParam('metadata.annotations.fallback.enabled', true);
    }

    private function engineVersion(): string
    {
        $version = (string)Config::getParam('metadata.engine.version', 'v3');
        return $version !== '' ? $version : 'v3';
    }

    private function softTtl(): int
    {
        return max(1, (int)Config::getParam('metadata.engine.soft_ttl', 300));
    }

    private function hardTtl(): int
    {
        $hard = max(1, (int)Config::getParam('metadata.engine.hard_ttl', 900));
        return max($hard, $this->softTtl());
    }

    private function effectiveSoftTtl(): int
    {
        if ($this->debugEnabled()) {
            return 0;
        }
        return $this->softTtl();
    }

    private function effectiveHardTtl(): int
    {
        if ($this->debugEnabled()) {
            return 0;
        }
        return $this->hardTtl();
    }

    private function swrEnabled(): bool
    {
        return !$this->debugEnabled() && (bool)Config::getParam('metadata.engine.swr.enabled', true);
    }

    private function redisEnabled(): bool
    {
        $mode = $this->cacheMode();
        if ($mode === CacheModeHelper::MODE_MEMORY || $mode === CacheModeHelper::MODE_OPCACHE) {
            return false;
        }
        if ($mode === CacheModeHelper::MODE_REDIS) {
            return true;
        }
        if (!(bool)Config::getParam('metadata.engine.redis.enabled', true)) {
            return false;
        }
        return (bool)Config::getParam('psfs.redis', false);
    }

    private function opcacheEnabled(): bool
    {
        $mode = $this->cacheMode();
        if ($mode === CacheModeHelper::MODE_MEMORY || $mode === CacheModeHelper::MODE_REDIS) {
            return false;
        }
        if ($mode !== CacheModeHelper::MODE_OPCACHE && !(bool)Config::getParam('metadata.engine.opcache.enabled', true)) {
            return false;
        }
        if (!extension_loaded('Zend OPcache')) {
            return false;
        }
        if (!$this->debugEnabled()) {
            return true;
        }
        $validateTimestamps = filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOLEAN);
        $revalidateFreq = (int)ini_get('opcache.revalidate_freq');
        if ($validateTimestamps && $revalidateFreq === 0) {
            return true;
        }
        if (!self::$debugOpcacheWarningLogged) {
            self::$debugOpcacheWarningLogged = true;
            Logger::log(
                '[MetadataEngine] Disabled opcache layer in debug mode: require opcache.validate_timestamps=1 and opcache.revalidate_freq=0',
                LOG_WARNING
            );
        }
        return false;
    }

    private function regenLockTtl(): int
    {
        return max(1, (int)Config::getParam('metadata.engine.regen.lock_ttl', 15));
    }

    private function engineEnabled(): bool
    {
        $mode = $this->cacheMode();
        if ($mode === CacheModeHelper::MODE_MEMORY) {
            return true;
        }
        if ($mode === CacheModeHelper::MODE_OPCACHE) {
            return true;
        }
        if ($mode === CacheModeHelper::MODE_REDIS) {
            return true;
        }
        return (bool)Config::getParam('metadata.engine.enabled', true);
    }

    private function rememberLegacyFallback(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackLogs)) {
            return;
        }
        self::$legacyFallbackLogs[$context] = true;
        Logger::log('[LegacyMetadata] ' . $context, LOG_NOTICE);
    }

    /**
     * @return array<string, mixed>|null
     */
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

    private function cacheMode(): string
    {
        return CacheModeHelper::normalize(Config::getParam('psfs.cache.mode', CacheModeHelper::MODE_NONE));
    }

    private function localCacheEnabled(): bool
    {
        return match ($this->cacheMode()) {
            CacheModeHelper::MODE_MEMORY => true,
            CacheModeHelper::MODE_OPCACHE, CacheModeHelper::MODE_REDIS => false,
            default => true,
        };
    }
}
