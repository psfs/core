<?php

namespace PSFS\base\queue;

class QueueBackendFactory
{
    /**
     * Redis is the primary backend. The fallback queue (files by default) takes over when Redis is down at
     * start or fails later, and is drained once Redis answers again, so no job is stranded in either of them.
     *
     * @param null|callable():RedisJobQueue $redisFactory
     */
    public static function createPersistent(?JobQueueInterface $fallback = null, ?callable $redisFactory = null): JobQueueInterface
    {
        $redis = $redisFactory ? $redisFactory() : new RedisJobQueue();
        return new FallbackJobQueue($redis, $fallback ?: new FileJobQueue());
    }
}
