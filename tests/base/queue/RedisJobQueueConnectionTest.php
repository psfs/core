<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\RedisJobQueue;

/**
 * Connection handling of RedisJobQueue: a real Redis when the environment has one, and failures otherwise
 */
class RedisJobQueueConnectionTest extends TestCase
{
    private const ENV = ['PSFS_REDIS_HOST', 'PSFS_REDIS_PORT', 'PSFS_REDIS_TIMEOUT'];

    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (self::ENV as $name) {
            $this->envBackup[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            false === $value ? putenv($name) : putenv($name . '=' . $value);
        }
    }

    private function realRedisHost(): string
    {
        if (!class_exists(\Redis::class)) {
            self::markTestSkipped('The redis extension is not loaded');
        }
        $host = $this->envBackup['PSFS_REDIS_HOST'] ?: 'redis';
        $socket = @fsockopen($host, 6379, $errno, $error, 0.5);
        if (false === $socket) {
            self::markTestSkipped('No Redis server reachable at ' . $host . ':6379');
        }
        fclose($socket);
        return $host;
    }

    public function testConnectsToTheConfiguredRedisAndMovesJobs(): void
    {
        $host = $this->realRedisHost();
        putenv('PSFS_REDIS_HOST=' . $host);
        $prefix = 'psfs:test:' . uniqid('', true);
        $queue = new RedisJobQueue(null, $prefix);

        try {
            self::assertTrue($queue->isAvailable());
            self::assertTrue($queue->enqueue('jobs', ['id' => 1]));
            self::assertTrue($queue->enqueue('jobs', ['id' => 2]));
            self::assertSame(['id' => 1], $queue->dequeue('jobs'));
            self::assertSame(['id' => 2], $queue->dequeue('jobs'));
            self::assertNull($queue->dequeue('jobs'));
        } finally {
            $redis = new \Redis();
            $redis->connect($host, 6379, 0.5);
            $redis->del($prefix . ':jobs');
        }
    }

    public function testAnUnreachableRedisLeavesTheQueueUnavailable(): void
    {
        if (!class_exists(\Redis::class)) {
            self::markTestSkipped('The redis extension is not loaded');
        }
        putenv('PSFS_REDIS_HOST=redis.invalid');
        putenv('PSFS_REDIS_TIMEOUT=0.1');

        $queue = new RedisJobQueue(null, 'psfs:test:unreachable');

        self::assertFalse($queue->isAvailable());
        self::assertFalse($queue->enqueue('jobs', ['id' => 1]));
        self::assertNull($queue->dequeue('jobs'));
    }

    public function testAClientThatFailsWhileReadingIsDroppedAndReportedAsUnavailable(): void
    {
        $client = new class {
            public function lPop(string $key)
            {
                throw new \RuntimeException('connection lost');
            }
        };
        $queue = new RedisJobQueue($client, 'psfs:test:broken');
        self::assertTrue($queue->isAvailable());

        self::assertNull($queue->dequeue('jobs'));

        self::assertFalse($queue->isAvailable(), 'The broken connection is discarded and retried later');
    }

    public function testMalformedPayloadsAreSkippedInsteadOfBreakingTheWorker(): void
    {
        $client = new class {
            public function lPop(string $key)
            {
                return 'this is not json';
            }
        };

        self::assertNull((new RedisJobQueue($client, 'psfs:test:malformed'))->dequeue('jobs'));
    }
}
