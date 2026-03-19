<?php

namespace PSFS\base\queue;

use PSFS\base\config\Config;
use PSFS\base\Logger;

class RedisJobQueue implements JobQueueInterface
{
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_TIMEOUT = 0.2;

    private ?object $redis = null;
    private string $prefix;

    public function __construct(?object $redis = null, ?string $prefix = null, bool $autoConnect = true)
    {
        $this->prefix = (string)($prefix ?: Config::getParam('job.queue.redis.prefix', 'psfs:queue'));
        $this->redis = $redis ?: ($autoConnect ? $this->connectRedis() : null);
    }

    public function enqueue(string $queue, array $payload): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return false !== $this->redis->rPush($this->queueKey($queue), json_encode($payload));
        } catch (\Throwable $exception) {
            Logger::log('[RedisJobQueue] enqueue failed: ' . $exception->getMessage(), LOG_WARNING);
            return false;
        }
    }

    public function dequeue(string $queue): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $raw = $this->redis->lPop($this->queueKey($queue));
            if (false === $raw || null === $raw) {
                return null;
            }
            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $exception) {
            Logger::log('[RedisJobQueue] dequeue failed: ' . $exception->getMessage(), LOG_WARNING);
            return null;
        }
    }

    public function isAvailable(): bool
    {
        return null !== $this->redis;
    }

    private function queueKey(string $queue): string
    {
        return $this->prefix . ':' . $queue;
    }

    private function connectRedis(): ?object
    {
        if (!class_exists('\Redis')) {
            return null;
        }
        $host = (string)(getenv('PSFS_REDIS_HOST') ?: Config::getParam('redis.host', ''));
        if ('' === $host) {
            $host = 'core-redis-1';
        }
        $port = (int)(getenv('PSFS_REDIS_PORT') ?: Config::getParam('redis.port', self::REDIS_DEFAULT_PORT));
        $timeout = (float)(getenv('PSFS_REDIS_TIMEOUT') ?: Config::getParam('redis.timeout', self::REDIS_DEFAULT_TIMEOUT));

        try {
            $redis = new \Redis();
            if ($redis->connect($host, $port, $timeout)) {
                return $redis;
            }
        } catch (\Throwable $exception) {
            Logger::log('[RedisJobQueue] redis unavailable: ' . $exception->getMessage(), LOG_NOTICE);
        }
        return null;
    }
}
