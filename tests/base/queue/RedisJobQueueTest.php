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

    public function testReconnectsOnceTheIntervalHasPassed(): void
    {
        FlakyRedisJobQueue::reset();
        $queue = new FlakyRedisJobQueue(null, 'test:queue');

        $this->assertFalse($queue->isAvailable());
        $this->assertSame(1, FlakyRedisJobQueue::$connectAttempts, 'only the constructor tried so far');

        FlakyRedisJobQueue::$clients[] = FlakyRedisJobQueue::client();
        FlakyRedisJobQueue::$now += 4;
        $this->assertFalse($queue->isAvailable(), 'reconnection is throttled');
        $this->assertSame(1, FlakyRedisJobQueue::$connectAttempts);

        FlakyRedisJobQueue::$now += 1;
        $this->assertTrue($queue->isAvailable());
        $this->assertSame(2, FlakyRedisJobQueue::$connectAttempts);
        $this->assertTrue($queue->enqueue('jobs', ['id' => 1]));
    }

    public function testBrokenConnectionIsDroppedAndRestoredLater(): void
    {
        FlakyRedisJobQueue::reset();
        FlakyRedisJobQueue::$clients[] = FlakyRedisJobQueue::client(true);
        $queue = new FlakyRedisJobQueue(null, 'test:queue');
        $this->assertTrue($queue->isAvailable());

        $this->assertFalse($queue->enqueue('jobs', ['id' => 1]));
        $this->assertFalse($queue->isAvailable(), 'the broken connection is discarded');

        FlakyRedisJobQueue::$clients[] = FlakyRedisJobQueue::client();
        FlakyRedisJobQueue::$now += 5;
        $this->assertTrue($queue->isAvailable());
        $this->assertTrue($queue->enqueue('jobs', ['id' => 1]));
    }

    public function testInjectedClientWithoutAutoConnectIsNeverDropped(): void
    {
        $queue = new RedisJobQueue(FlakyRedisJobQueue::client(true), 'test:queue', false);

        $this->assertFalse($queue->enqueue('jobs', ['id' => 1]));
        $this->assertTrue($queue->isAvailable());
    }
}

/**
 * Redis queue whose connection attempts and clock are scripted by the tests
 */
class FlakyRedisJobQueue extends RedisJobQueue
{
    /** @var object[] clients handed out by the next connection attempts; none left means Redis is down */
    public static array $clients = [];
    public static int $connectAttempts = 0;
    public static int $now = 1000;

    public static function reset(): void
    {
        self::$clients = [];
        self::$connectAttempts = 0;
        self::$now = 1000;
    }

    public static function client(bool $failing = false): object
    {
        return new class($failing) {
            public function __construct(private bool $failing)
            {
            }

            public function rPush(string $key, string $payload)
            {
                return $this->failing ? throw new \RuntimeException('redis down') : 1;
            }

            public function lPop(string $key)
            {
                return $this->failing ? throw new \RuntimeException('redis down') : false;
            }
        };
    }

    protected function connectRedis(): ?object
    {
        self::$connectAttempts++;
        return array_shift(self::$clients);
    }

    protected function now(): int
    {
        return self::$now;
    }
}
