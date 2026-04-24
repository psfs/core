<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\CacheModeHelper;

final class MetadataEngineConfig
{
    private static bool $debugOpcacheWarningLogged = false;

    public function debugEnabled(): bool
    {
        return (bool)Config::getParam('debug', false);
    }

    public function attributesEnabled(): bool
    {
        return (bool)Config::getParam('metadata.attributes.enabled', true);
    }

    public function annotationsFallbackEnabled(): bool
    {
        return (bool)Config::getParam('metadata.annotations.fallback.enabled', true);
    }

    public function engineVersion(): string
    {
        $version = (string)Config::getParam('metadata.engine.version', 'v3');
        return $version !== '' ? $version : 'v3';
    }

    public function effectiveSoftTtl(): int
    {
        return $this->debugEnabled() ? 0 : $this->softTtl();
    }

    public function effectiveHardTtl(): int
    {
        return $this->debugEnabled() ? 0 : $this->hardTtl();
    }

    public function swrEnabled(): bool
    {
        return !$this->debugEnabled() && (bool)Config::getParam('metadata.engine.swr.enabled', true);
    }

    public function redisEnabled(): bool
    {
        $mode = $this->cacheMode();
        if ($mode === CacheModeHelper::MODE_REDIS) {
            return true;
        }
        if ($mode === CacheModeHelper::MODE_MEMORY || $mode === CacheModeHelper::MODE_OPCACHE) {
            return false;
        }
        return (bool)Config::getParam('metadata.engine.redis.enabled', true)
            && (bool)Config::getParam('psfs.redis', false);
    }

    public function opcacheEnabled(): bool
    {
        $mode = $this->cacheMode();
        if ($mode === CacheModeHelper::MODE_MEMORY || $mode === CacheModeHelper::MODE_REDIS) {
            return false;
        }
        if (!$this->opcacheLayerAllowed($mode)) {
            return false;
        }
        return !$this->debugEnabled() || $this->debugOpcacheEnabled();
    }

    public function regenLockTtl(): int
    {
        return max(1, (int)Config::getParam('metadata.engine.regen.lock_ttl', 15));
    }

    public function engineEnabled(): bool
    {
        $mode = $this->cacheMode();
        if (in_array($mode, [CacheModeHelper::MODE_MEMORY, CacheModeHelper::MODE_OPCACHE, CacheModeHelper::MODE_REDIS], true)) {
            return true;
        }
        return (bool)Config::getParam('metadata.engine.enabled', true);
    }

    public function localCacheEnabled(): bool
    {
        return match ($this->cacheMode()) {
            CacheModeHelper::MODE_MEMORY => true,
            CacheModeHelper::MODE_OPCACHE, CacheModeHelper::MODE_REDIS => false,
            default => true,
        };
    }

    public function cacheMode(): string
    {
        return CacheModeHelper::normalize(Config::getParam('psfs.cache.mode', CacheModeHelper::MODE_NONE));
    }

    private function softTtl(): int
    {
        return max(1, (int)Config::getParam('metadata.engine.soft_ttl', 300));
    }

    private function hardTtl(): int
    {
        return max(max(1, (int)Config::getParam('metadata.engine.hard_ttl', 900)), $this->softTtl());
    }

    private function opcacheLayerAllowed(string $mode): bool
    {
        if ($mode !== CacheModeHelper::MODE_OPCACHE && !(bool)Config::getParam('metadata.engine.opcache.enabled', true)) {
            return false;
        }
        return extension_loaded('Zend OPcache');
    }

    private function debugOpcacheEnabled(): bool
    {
        $validateTimestamps = filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOLEAN);
        $revalidateFreq = (int)ini_get('opcache.revalidate_freq');
        if ($validateTimestamps && $revalidateFreq === 0) {
            return true;
        }
        $this->logDebugOpcacheWarning();
        return false;
    }

    private function logDebugOpcacheWarning(): void
    {
        if (self::$debugOpcacheWarningLogged) {
            return;
        }
        self::$debugOpcacheWarningLogged = true;
        Logger::log(
            '[MetadataEngine] Disabled opcache layer in debug mode: require opcache.validate_timestamps=1 and opcache.revalidate_freq=0',
            LOG_WARNING
        );
    }
}
