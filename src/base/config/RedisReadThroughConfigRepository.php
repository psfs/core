<?php

namespace PSFS\base\config;

use PSFS\base\Logger;

class RedisReadThroughConfigRepository implements ConfigRepositoryInterface
{
    private const REDIS_DEFAULT_HOST = '127.0.0.1';
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_TIMEOUT = 1.5;

    protected FileConfigRepository $fileRepository;
    protected int $ttl;
    protected string $version;
    protected ?\Redis $redis = null;

    public function __construct(FileConfigRepository $fileRepository, int $ttl = 60, string $version = 'v1')
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
            Logger::log('[Config][Redis] ' . $exception->getMessage(), LOG_WARNING);
            return $this->fileRepository->read();
        }
    }

    public function save(array $data): bool
    {
        $saved = $this->fileRepository->save($data);
        $this->invalidate();
        return $saved;
    }

    public function refresh(): array
    {
        $this->invalidate();
        return $this->read();
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
            Logger::log('[Config][Redis] ' . $exception->getMessage(), LOG_WARNING);
        }
    }

    public function getConfigPath(): string
    {
        return $this->fileRepository->getConfigPath();
    }

    private function connectRedis(): void
    {
        if (!class_exists(\Redis::class)) {
            return;
        }
        $host = getenv('PSFS_REDIS_HOST') ?: self::REDIS_DEFAULT_HOST;
        $port = (int)(getenv('PSFS_REDIS_PORT') ?: self::REDIS_DEFAULT_PORT);
        $timeout = (float)(getenv('PSFS_REDIS_TIMEOUT') ?: self::REDIS_DEFAULT_TIMEOUT);
        try {
            $redis = new \Redis();
            if (!$redis->connect($host, $port, $timeout)) {
                return;
            }
            $this->redis = $redis;
        } catch (\RedisException $exception) {
            Logger::log('[Config][Redis] ' . $exception->getMessage(), LOG_WARNING);
            $this->redis = null;
        }
    }

    private function buildReadThroughKey(): string
    {
        $pathHash = sha1($this->fileRepository->getConfigPath());
        $signature = $this->fileRepository->getFileSignature();
        return implode(':', ['psfs', 'config', $pathHash, $this->version, sha1($signature)]);
    }

    private function getLatestKey(): string
    {
        return 'psfs:config:latest:' . sha1($this->fileRepository->getConfigPath()) . ':' . $this->version;
    }
}

