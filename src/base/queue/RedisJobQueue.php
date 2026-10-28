<?php

namespace PSFS\base\queue;

use PSFS\base\config\Config;
use PSFS\base\Logger;

class RedisJobQueue implements JobQueueInterface
{
    private const REDIS_DEFAULT_PORT = 6379;
    private const REDIS_DEFAULT_TIMEOUT = 0.2;
    private const RECONNECT_INTERVAL = 5;

    private ?object $redis = null;
    private string $prefix;
    private bool $autoConnect;
    private int $nextReconnectAt = 0;
    private bool $unavailableLogged = false;

    public function __construct(?object $redis = null, ?string $prefix = null, bool $autoConnect = true)
    {
        $this->prefix = (string)($prefix ?: Config::getParam('job.queue.redis.prefix', 'psfs:queue'));
        $this->autoConnect = $autoConnect;
        $this->redis = $redis ?: ($autoConnect ? $this->connectRedis() : null);
        $this->scheduleReconnect();
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
            $this->dropConnection();
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
            $this->dropConnection();
            return null;
        }
    }

    public function isAvailable(): bool
    {
        if (null === $this->redis && $this->autoConnect && $this->now() >= $this->nextReconnectAt) {
            $this->redis = $this->connectRedis();
            $this->scheduleReconnect();
        }
        return null !== $this->redis;
    }

    protected function now(): int
    {
        return time();
    }

    /**
     * A broken connection is discarded so it is retried later, throttled by RECONNECT_INTERVAL
     */
    private function dropConnection(): void
    {
        if ($this->autoConnect) {
            $this->redis = null;
            $this->scheduleReconnect();
        }
    }

    private function scheduleReconnect(): void
    {
        $this->nextReconnectAt = $this->now() + self::RECONNECT_INTERVAL;
    }

    private function queueKey(string $queue): string
    {
        return $this->prefix . ':' . $queue;
    }

    protected function connectRedis(): ?object
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

        $reason = '';
        error_clear_last();
        try {
            $redis = new \Redis();
            // phpredis reports an unreachable host as a PHP warning on top of the false/exception: keep only ours
            if (@$redis->connect($host, $port, $timeout)) {
                $this->unavailableLogged = false;
                return $redis;
            }
            $reason = (string)(error_get_last()['message'] ?? 'connection failed');
        } catch (\Throwable $exception) {
            $reason = $exception->getMessage();
        }
        // Reconnection attempts must not repeat the same warning for the whole outage
        if (!$this->unavailableLogged) {
            $this->unavailableLogged = true;
            Logger::log('[RedisJobQueue] redis unavailable: ' . $reason, LOG_WARNING);
        }
        return null;
    }
}
