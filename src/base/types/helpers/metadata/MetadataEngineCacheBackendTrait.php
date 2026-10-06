<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\config\Config;
use PSFS\base\Logger;

/**
 * Redis and OPcache artifact storage for metadata cache entries.
 *
 * Requires MetadataEngine configuration trait, Redis connection state, and cache key constants.
 */
trait MetadataEngineCacheBackendTrait
{
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
            if (!@opcache_invalidate($path, true)) {
                Logger::log('[MetadataEngine] Unable to invalidate opcache artifact: ' . $path, LOG_DEBUG);
            }
        }
        if (function_exists('opcache_compile_file')) {
            if (!@opcache_compile_file($path)) {
                Logger::log('[MetadataEngine] Unable to compile opcache artifact: ' . $path, LOG_DEBUG);
            }
        }
    }

    protected function dropOpcacheArtifact(string $cacheKey): void
    {
        $path = $this->artifactPath($cacheKey);
        if (function_exists('opcache_invalidate')) {
            if (!@opcache_invalidate($path, true)) {
                Logger::log('[MetadataEngine] Unable to invalidate opcache artifact: ' . $path, LOG_DEBUG);
            }
        }
        if (file_exists($path)) {
            if (!@unlink($path)) {
                Logger::log('[MetadataEngine] Unable to remove opcache artifact: ' . $path, LOG_WARNING);
            }
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

}
