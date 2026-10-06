<?php

namespace PSFS\base\types\helpers\metadata;

/**
 * Public metadata engine facade and shared cache state.
 *
 * The implementation is split into focused traits so each subsystem can be
 * reviewed and tested through a small, stable engine API.
 */
class MetadataEngine implements MetadataEngineInterface
{
    use MetadataEngineAttributeApiTrait;
    use MetadataEngineBundleTrait;
    use MetadataEngineCacheEntryTrait;
    use MetadataEngineCacheBackendTrait;
    use MetadataEngineRegenerationTrait;
    use MetadataEngineConfigurationTrait;

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
    private ?MetadataAttributeBundleBuilder $attributeBundleBuilder = null;
    private ?MetadataEngineConfig $engineConfig = null;

    /**
     * @return array<string, int|float>
     */
    public function getStats(): array
    {
        return self::$stats;
    }

    public function clearLocalCache(): void
    {
        self::$localCache = [];
    }
}
