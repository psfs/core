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

    public function testEnqueueReturnsFalseWhenPayloadCannotBeEncoded(): void
    {
        $queue = new FileJobQueue($this->queueDir);
        $this->assertFalse($queue->enqueue('bad', ['inf' => INF]));
    }

    public function testQueueFallbackSlugDefaultsToDefaultWhenQueueNameIsEmpty(): void
    {
        $queue = new FileJobQueue($this->queueDir);
        $this->assertTrue($queue->enqueue('###', ['id' => 1]));

        $files = glob($this->queueDir . DIRECTORY_SEPARATOR . '*.queue');
        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertStringContainsString('default-', basename($files[0]));
    }

    public function testDequeueReturnsNullWhenInputCannotBeOpened(): void
    {
        $dir = $this->queueDir . DIRECTORY_SEPARATOR . 'as-dir';
        mkdir($dir, 0775, true);

        $queue = new class($this->queueDir, $dir) extends FileJobQueue {
            public function __construct(private string $base, private string $forcedPath)
            {
                parent::__construct($base);
            }

            protected function queueFile(string $queue): string
            {
                return $this->forcedPath;
            }
        };

        $this->assertNull($queue->dequeue('ignored'));
    }

    public function testDequeueReturnsNullWhenTempExtractionFails(): void
    {
        $queue = new class($this->queueDir) extends FileJobQueue {
            protected function dequeueIntoTemp($input, $output): ?array
            {
                fclose($input);
                fclose($output);
                return null;
            }
        };
        $queue->enqueue('events', ['id' => 1]);
        $this->assertNull($queue->dequeue('events'));
    }

    public function testDequeueReturnsNullWhenFirstLineIsMissing(): void
    {
        $queue = new class($this->queueDir) extends FileJobQueue {
            protected function dequeueIntoTemp($input, $output): ?array
            {
                fclose($input);
                fclose($output);
                return ['first' => null, 'has_remaining' => false];
            }
        };
        $queue->enqueue('events', ['id' => 1]);
        $this->assertNull($queue->dequeue('events'));
    }

    public function testDequeueReturnsNullWhenCommitFails(): void
    {
        $queue = new class($this->queueDir) extends FileJobQueue {
            protected function commitDequeuedState(string $queueFile, string $tmpPath, bool $hasRemaining): bool
            {
                if (file_exists($tmpPath)) {
                    unlink($tmpPath);
                }
                return false;
            }
        };
        $queue->enqueue('events', ['id' => 1]);
        $this->assertNull($queue->dequeue('events'));
    }

    public function testDequeueUsingFullReadReturnsNullForInvalidJson(): void
    {
        $queue = new class($this->queueDir) extends FileJobQueue {
            public function exposeQueueFile(string $queue): string
            {
                return $this->queueFile($queue);
            }

            public function exposeDequeueUsingFullRead(string $queueFile): ?array
            {
                return $this->dequeueUsingFullRead($queueFile);
            }
        };

        $queueFile = $queue->exposeQueueFile('events');
        $dir = dirname($queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($queueFile, "not-json\n{\"id\":2}\n");

        $this->assertNull($queue->exposeDequeueUsingFullRead($queueFile));
        $this->assertSame(1, $queue->size('events'));
    }
}
