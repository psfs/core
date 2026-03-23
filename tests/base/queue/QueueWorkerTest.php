<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueJobInterface;
use PSFS\base\queue\QueueWorker;
use PSFS\base\queue\SyncJobQueue;

class QueueWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        WorkerTestJob::$handled = [];
    }

    public function testWorkerProcessesAvailableJobsAndStopsWhenQueueBecomesEmpty(): void
    {
        $queue = new SyncJobQueue();
        $dispatcher = new QueueDispatcher($queue, new JobRegistry([WorkerTestJob::class], []));
        $dispatcher->dispatch('worker-test', ['id' => 1]);
        $dispatcher->dispatch('worker-test', ['id' => 2]);

        $processed = (new QueueWorker($dispatcher))->work('worker-test', 0, 1000, true);

        $this->assertSame(2, $processed);
        $this->assertSame([['id' => 1], ['id' => 2]], WorkerTestJob::$handled);
    }
}

class WorkerTestJob implements QueueJobInterface
{
    public static array $handled = [];

    public function __construct(private readonly array $payload)
    {
    }

    public static function code(): string
    {
        return 'worker-test';
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function handle(): void
    {
        self::$handled[] = $this->payload;
    }
}
