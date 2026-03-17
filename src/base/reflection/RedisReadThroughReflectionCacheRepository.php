<?php

namespace PSFS\base\reflection;

use PSFS\base\config\Config;
use PSFS\base\Logger;

class RedisReadThroughReflectionCacheRepository implements ReflectionCacheRepositoryInterface
{
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_TIMEOUT = 0.25;

    protected FileReflectionCacheRepository $fileRepository;
    protected int $ttl;
    protected string $version;
    protected ?\Redis $redis = null;

    public function __construct(FileReflectionCacheRepository $fileRepository, int $ttl = 300, string $version = 'v1')
    {
        $this->fileRepository = $fileRepository;
        $this->ttl = max(1, $ttl);
        $this->version = $version ?: 'v1';
        $this->connectRedis();
    }

    public function read(): array
    {
        if (null === $this->redis) {
            return $this->fileRepository->read();
        }
        $key = $this->buildReadThroughKey();
        try {
            $cached = $this->redis->get($key);
            if (is_string($cached) && '' !== $cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            $data = $this->fileRepository->read();
            $this->redis->setex($key, $this->ttl, json_encode($data));
            $this->redis->set($this->getLatestKey(), $key);
            return $data;
        } catch (\RedisException $exception) {
            Logger::log('[Reflections][Redis] ' . $exception->getMessage(), LOG_WARNING);
            return $this->fileRepository->read();
        }
    }

    public function save(array $properties): bool
    {
        $saved = $this->fileRepository->save($properties);
        if (!$saved) {
            return false;
        }
        $this->invalidate();
        if (null !== $this->redis) {
            try {
                $key = $this->buildReadThroughKey();
                $this->redis->setex($key, $this->ttl, json_encode($properties));
                $this->redis->set($this->getLatestKey(), $key);
            } catch (\RedisException $exception) {
                Logger::log('[Reflections][Redis] ' . $exception->getMessage(), LOG_WARNING);
            }
        }
        return true;
    }

    public function refresh(): array
    {
        $this->invalidate();
        return $this->fileRepository->read();
    }

    public function invalidate(): void
    {
        if (null === $this->redis) {
            return;
        }
        try {
            $latest = $this->redis->get($this->getLatestKey());
            if (is_string($latest) && '' !== $latest) {
                $this->redis->del($latest);
            }
            $this->redis->del($this->getLatestKey());
        } catch (\RedisException $exception) {
            Logger::log('[Reflections][Redis] ' . $exception->getMessage(), LOG_WARNING);
        }
    }

    public function getCachePath(): string
    {
        return $this->fileRepository->getCachePath();
    }

    public function getSourceSignature(): string
    {
        return $this->fileRepository->getSourceSignature();
    }

    private function connectRedis(): void
    {
        if (!class_exists(\Redis::class)) {
            return;
        }

        $hosts = array_values(array_filter(array_unique([
            getenv('PSFS_REDIS_HOST') ?: null,
            (string)Config::getParam('redis.host', ''),
            'redis',
            'core-redis-1',
            '127.0.0.1',
        ])));
        $port = (int)(getenv('PSFS_REDIS_PORT') ?: Config::getParam('redis.port', self::REDIS_DEFAULT_PORT));
        $timeout = (float)(getenv('PSFS_REDIS_TIMEOUT') ?: Config::getParam('redis.timeout', self::REDIS_DEFAULT_TIMEOUT));

        foreach ($hosts as $host) {
            try {
                $redis = new \Redis();
                if ($redis->connect($host, $port, $timeout)) {
                    $this->redis = $redis;
                    return;
                }
            } catch (\RedisException $exception) {
                Logger::log('[Reflections][Redis] ' . $exception->getMessage(), LOG_WARNING);
            }
        }
        $this->redis = null;
    }

    private function buildReadThroughKey(): string
    {
        return implode(':', [
            'psfs',
            'reflections',
            sha1($this->fileRepository->getClassName()),
            $this->version,
            sha1($this->fileRepository->getSourceSignature()),
        ]);
    }

    private function getLatestKey(): string
    {
        return 'psfs:reflections:latest:' . sha1($this->fileRepository->getClassName()) . ':' . $this->version;
    }
}
