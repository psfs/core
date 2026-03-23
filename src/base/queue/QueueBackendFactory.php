<?php

namespace PSFS\base\queue;

class QueueBackendFactory
{
    /**
     * @param null|callable():RedisJobQueue $redisFactory
     */
    public static function createPersistent(?JobQueueInterface $fallback = null, ?callable $redisFactory = null): JobQueueInterface
    {
        $redis = $redisFactory ? $redisFactory() : new RedisJobQueue();
        if ($redis->isAvailable()) {
            return $redis;
        }
        return $fallback ?: new FileJobQueue();
    }
}
