<?php

namespace PSFS\base\queue;

class QueueBackendFactory
{
    public static function createPersistent(?JobQueueInterface $fallback = null): JobQueueInterface
    {
        $redis = new RedisJobQueue();
        if ($redis->isAvailable()) {
            return $redis;
        }
        return $fallback ?: new FileJobQueue();
    }
}
