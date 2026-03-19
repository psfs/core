<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\SyncJobQueue;

class SyncJobQueueTest extends TestCase
{
    public function testEnqueueAndDequeueFIFO(): void
    {
        $queue = new SyncJobQueue();

        $queue->enqueue('jobs', ['id' => 1]);
        $queue->enqueue('jobs', ['id' => 2]);

        $this->assertSame(['id' => 1], $queue->dequeue('jobs'));
        $this->assertSame(['id' => 2], $queue->dequeue('jobs'));
        $this->assertNull($queue->dequeue('jobs'));
    }

    public function testSizeAndAvailability(): void
    {
        $queue = new SyncJobQueue();
        $this->assertTrue($queue->isAvailable());
        $this->assertSame(0, $queue->size('jobs'));

        $queue->enqueue('jobs', ['type' => 'log']);
        $this->assertSame(1, $queue->size('jobs'));
    }
}
