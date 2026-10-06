<?php

namespace PSFS\base\types\helpers\metadata;

/**
 * Cached access to metadata engine feature flags and TTL configuration.
 *
 * Requires the cached engine configuration state declared by MetadataEngine.
 */
trait MetadataEngineConfigurationTrait
{
    private function debugEnabled(): bool
    {
        return $this->engineConfig()->debugEnabled();
    }

    private function attributesEnabled(): bool
    {
        return $this->engineConfig()->attributesEnabled();
    }

    private function annotationsFallbackEnabled(): bool
    {
        return $this->engineConfig()->annotationsFallbackEnabled();
    }

    private function engineVersion(): string
    {
        return $this->engineConfig()->engineVersion();
    }

    private function effectiveSoftTtl(): int
    {
        return $this->engineConfig()->effectiveSoftTtl();
    }

    private function effectiveHardTtl(): int
    {
        return $this->engineConfig()->effectiveHardTtl();
    }

    private function swrEnabled(): bool
    {
        return $this->engineConfig()->swrEnabled();
    }

    private function redisEnabled(): bool
    {
        return $this->engineConfig()->redisEnabled();
    }

    private function opcacheEnabled(): bool
    {
        return $this->engineConfig()->opcacheEnabled();
    }

    private function regenLockTtl(): int
    {
        return $this->engineConfig()->regenLockTtl();
    }

    private function engineEnabled(): bool
    {
        return $this->engineConfig()->engineEnabled();
    }

    private function cacheMode(): string
    {
        return $this->engineConfig()->cacheMode();
    }

    private function localCacheEnabled(): bool
    {
        return $this->engineConfig()->localCacheEnabled();
    }

    private function engineConfig(): MetadataEngineConfig
    {
        $config = $this->engineConfig;
        if (!$config instanceof MetadataEngineConfig) {
            $config = new MetadataEngineConfig();
            $this->engineConfig = $config;
        }
        return $config;
    }

}
