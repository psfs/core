<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\FileJobQueue;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\SyncJobQueue;

class QueueBackendFactoryTest extends TestCase
{
    public function testReturnsRedisBackendWhenItIsAvailable(): void
    {
        $redisQueue = new \PSFS\base\queue\RedisJobQueue(new class {
        }, 'tests:queue', false);
        $fallback = new SyncJobQueue();

        $backend = QueueBackendFactory::createPersistent($fallback, static fn () => $redisQueue);

        $this->assertSame($redisQueue, $backend);
    }

    public function testReturnsFallbackWhenRedisIsUnavailable(): void
    {
        $fallback = new SyncJobQueue();

        $backend = QueueBackendFactory::createPersistent(
            $fallback,
            static fn () => new \PSFS\base\queue\RedisJobQueue(null, 'tests:queue', false)
        );

        $this->assertSame($fallback, $backend);
    }

    public function testReturnsFileQueueWhenRedisIsUnavailableAndNoFallbackProvided(): void
    {
        $backend = QueueBackendFactory::createPersistent(
            null,
            static fn () => new \PSFS\base\queue\RedisJobQueue(null, 'tests:queue', false)
        );

        $this->assertInstanceOf(FileJobQueue::class, $backend);
    }
}
