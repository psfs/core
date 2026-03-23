<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueJobInterface;
use PSFS\base\queue\SyncJobQueue;

class QueueDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        DispatchTestJob::$handledPayloads = [];
    }

    public function testDispatchEnqueuesEnvelopeAndExecuteHandlesJob(): void
    {
        $queue = new SyncJobQueue();
        $dispatcher = new QueueDispatcher($queue, new JobRegistry([DispatchTestJob::class], []));

        $this->assertTrue($dispatcher->dispatch('notifications', ['message' => 'done']));

        $message = $dispatcher->consume('notifications');
        $this->assertSame('notifications', $message['code']);
        $this->assertSame(['message' => 'done'], $message['payload']);

        $dispatcher->execute($message);
        $this->assertSame([['message' => 'done']], DispatchTestJob::$handledPayloads);
    }
}

class DispatchTestJob implements QueueJobInterface
{
    public static array $handledPayloads = [];

    public function __construct(private readonly array $payload)
    {
    }

    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function handle(): void
    {
        self::$handledPayloads[] = $this->payload;
    }
}
