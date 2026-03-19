<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\RedisJobQueue;

class RedisJobQueueTest extends TestCase
{
    public function testUnavailableRedisReturnsSafeDefaults(): void
    {
        $queue = new RedisJobQueue(null, 'test:queue', false);

        $this->assertFalse($queue->isAvailable());
        $this->assertFalse($queue->enqueue('jobs', ['a' => 1]));
        $this->assertNull($queue->dequeue('jobs'));
    }

    public function testEnqueueAndDequeueWithRedisClient(): void
    {
        $redis = new class {
            public array $data = [];

            public function rPush(string $key, string $payload)
            {
                $this->data[$key][] = $payload;
                return count($this->data[$key]);
            }

            public function lPop(string $key)
            {
                if (empty($this->data[$key])) {
                    return false;
                }
                return array_shift($this->data[$key]);
            }
        };

        $queue = new RedisJobQueue($redis, 'test:queue');

        $this->assertTrue($queue->isAvailable());
        $this->assertTrue($queue->enqueue('jobs', ['id' => 10]));
        $this->assertSame(['id' => 10], $queue->dequeue('jobs'));
        $this->assertNull($queue->dequeue('jobs'));
    }

    public function testRedisExceptionsAreCaught(): void
    {
        $redis = new class {
            public function rPush(string $key, string $payload)
            {
                throw new \RuntimeException('redis down');
            }

            public function lPop(string $key)
            {
                throw new \RuntimeException('redis down');
            }
        };

        $queue = new RedisJobQueue($redis, 'test:queue');

        $this->assertFalse($queue->enqueue('jobs', ['x' => 1]));
        $this->assertNull($queue->dequeue('jobs'));
    }
}
