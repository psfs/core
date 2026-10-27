<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\FallbackJobQueue;
use PSFS\base\queue\FileJobQueue;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\RedisJobQueue;
use PSFS\base\queue\SyncJobQueue;

class QueueBackendFactoryTest extends TestCase
{
    public function testWritesToRedisWhileItIsAvailable(): void
    {
        $client = new class {
            public array $pushed = [];

            public function rPush(string $key, string $payload)
            {
                $this->pushed[] = $key;
                return count($this->pushed);
            }
        };
        $fallback = new SyncJobQueue();

        $backend = QueueBackendFactory::createPersistent(
            $fallback,
            static fn () => new RedisJobQueue($client, 'tests:queue', false)
        );

        $this->assertInstanceOf(FallbackJobQueue::class, $backend);
        $this->assertTrue($backend->enqueue('jobs', ['id' => 1]));
        $this->assertSame(['tests:queue:jobs'], $client->pushed);
        $this->assertSame(0, $fallback->size('jobs'));
    }

    public function testUsesTheFallbackWhenRedisIsUnavailable(): void
    {
        $fallback = new SyncJobQueue();

        $backend = QueueBackendFactory::createPersistent(
            $fallback,
            static fn () => new RedisJobQueue(null, 'tests:queue', false)
        );

        $this->assertInstanceOf(FallbackJobQueue::class, $backend);
        $this->assertTrue($backend->enqueue('jobs', ['id' => 1]));
        $this->assertSame(1, $fallback->size('jobs'));
        $this->assertSame(['id' => 1], $backend->dequeue('jobs'));
    }

    public function testFallsBackToTheFileQueueWhenNoFallbackIsProvided(): void
    {
        $backend = QueueBackendFactory::createPersistent(
            null,
            static fn () => new RedisJobQueue(null, 'tests:queue', false)
        );

        $this->assertInstanceOf(FallbackJobQueue::class, $backend);
        $secondary = (new \ReflectionProperty(FallbackJobQueue::class, 'secondary'))->getValue($backend);
        $this->assertInstanceOf(FileJobQueue::class, $secondary);
    }
}
