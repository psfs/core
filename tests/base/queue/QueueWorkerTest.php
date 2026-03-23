<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueJobInterface;
use PSFS\base\queue\QueueWorker;
use PSFS\base\queue\SyncJobQueue;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        WorkerTestJob::$handled = [];
        WorkerFailingJob::$executions = 0;
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

    public function testWorkerHonorsMaxJobsLimit(): void
    {
        $queue = new SyncJobQueue();
        $dispatcher = new QueueDispatcher($queue, new JobRegistry([WorkerTestJob::class], []));
        $dispatcher->dispatch('worker-test', ['id' => 1]);
        $dispatcher->dispatch('worker-test', ['id' => 2]);
        $dispatcher->dispatch('worker-test', ['id' => 3]);

        $processed = (new QueueWorker($dispatcher))->work('worker-test', 2, 1000, false);

        $this->assertSame(2, $processed);
        $this->assertSame([['id' => 1], ['id' => 2]], WorkerTestJob::$handled);
    }

    public function testWorkerWritesProcessedMessagesToOutput(): void
    {
        $queue = new SyncJobQueue();
        $dispatcher = new QueueDispatcher($queue, new JobRegistry([WorkerTestJob::class], []));
        $dispatcher->dispatch('worker-test', ['id' => 10]);
        $output = new BufferedOutput();

        $processed = (new QueueWorker($dispatcher))->work('worker-test', 1, 1000, false, $output);

        $this->assertSame(1, $processed);
        $this->assertStringContainsString('[queue] processed job worker-test on worker-test', $output->fetch());
    }

    public function testWorkerHandlesJobFailureAndKeepsRunning(): void
    {
        $queue = new SyncJobQueue();
        $dispatcher = new QueueDispatcher($queue, new JobRegistry([WorkerFailingJob::class, WorkerTestJob::class], []));
        $dispatcher->dispatch('worker-fail', ['id' => 99]);
        $dispatcher->dispatch('worker-test', ['id' => 100]);
        $output = new BufferedOutput();

        $processed = (new QueueWorker($dispatcher))->work('worker-fail', 0, 1000, true, $output);

        $this->assertSame(1, WorkerFailingJob::$executions);
        $this->assertSame(0, $processed);
        $this->assertStringContainsString('[queue] failed job worker-fail on worker-fail: boom from worker', $output->fetch());

        $processedSuccess = (new QueueWorker($dispatcher))->work('worker-test', 0, 1000, true, $output);

        $this->assertSame(1, $processedSuccess);
        $this->assertSame([['id' => 100]], WorkerTestJob::$handled);
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

class WorkerFailingJob implements QueueJobInterface
{
    public static int $executions = 0;

    public static function code(): string
    {
        return 'worker-fail';
    }

    public static function fromPayload(array $payload): self
    {
        return new self();
    }

    public function handle(): void
    {
        self::$executions++;
        throw new \RuntimeException('boom from worker');
    }
}
