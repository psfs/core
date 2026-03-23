<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\FileJobQueue;
use PSFS\base\types\helpers\GeneratorHelper;

class FileJobQueueTest extends TestCase
{
    private string $queueDir;

    protected function setUp(): void
    {
        $this->queueDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-file-queue-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        GeneratorHelper::deleteDir($this->queueDir);
    }

    public function testQueuePersistsAcrossInstances(): void
    {
        $first = new FileJobQueue($this->queueDir);
        $second = new FileJobQueue($this->queueDir);

        $this->assertTrue($first->enqueue('notifications', ['id' => 1]));
        $this->assertTrue($first->enqueue('notifications', ['id' => 2]));

        $this->assertSame(['id' => 1], $second->dequeue('notifications'));
        $this->assertSame(['id' => 2], $second->dequeue('notifications'));
        $this->assertNull($second->dequeue('notifications'));
    }

    public function testSizeReflectsQueuedMessages(): void
    {
        $queue = new FileJobQueue($this->queueDir);

        $this->assertSame(0, $queue->size('audit'));
        $queue->enqueue('audit', ['event' => 'login']);
        $queue->enqueue('audit', ['event' => 'logout']);

        $this->assertSame(2, $queue->size('audit'));
        $queue->dequeue('audit');
        $this->assertSame(1, $queue->size('audit'));
    }
}
