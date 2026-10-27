<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\FallbackJobQueue;
use PSFS\base\queue\JobQueueInterface;
use PSFS\base\queue\SyncJobQueue;

class FallbackJobQueueTest extends TestCase
{
    private function primary(bool $up = true): JobQueueInterface
    {
        return new class($up) implements JobQueueInterface {
            public SyncJobQueue $inner;

            public function __construct(public bool $up)
            {
                $this->inner = new SyncJobQueue();
            }

            public function enqueue(string $queue, array $payload): bool
            {
                return $this->up && $this->inner->enqueue($queue, $payload);
            }

            public function dequeue(string $queue): ?array
            {
                return $this->up ? $this->inner->dequeue($queue) : null;
            }

            public function isAvailable(): bool
            {
                return $this->up;
            }
        };
    }

    public function testEnqueueUsesThePrimaryQueueWhileItWorks(): void
    {
        $primary = $this->primary();
        $secondary = new SyncJobQueue();
        $queue = new FallbackJobQueue($primary, $secondary);

        $this->assertTrue($queue->enqueue('jobs', ['id' => 1]));

        $this->assertSame(1, $primary->inner->size('jobs'));
        $this->assertSame(0, $secondary->size('jobs'));
    }

    public function testEnqueueFallsBackWhenThePrimaryIsDown(): void
    {
        $primary = $this->primary(false);
        $secondary = new SyncJobQueue();
        $queue = new FallbackJobQueue($primary, $secondary);

        $this->assertTrue($queue->enqueue('jobs', ['id' => 1]));
        $this->assertTrue($queue->enqueue('jobs', ['id' => 2]));

        $this->assertSame(0, $primary->inner->size('jobs'));
        $this->assertSame(2, $secondary->size('jobs'));
    }

    public function testEnqueueFallsBackWhenThePrimaryFailsAtRuntime(): void
    {
        $primary = new class implements JobQueueInterface {
            public function enqueue(string $queue, array $payload): bool
            {
                return false;
            }

            public function dequeue(string $queue): ?array
            {
                return null;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
        $secondary = new SyncJobQueue();

        $this->assertTrue((new FallbackJobQueue($primary, $secondary))->enqueue('jobs', ['id' => 1]));
        $this->assertSame(1, $secondary->size('jobs'));
    }

    public function testEnqueueReportsFailureOnlyWhenBothQueuesFail(): void
    {
        $failing = new class implements JobQueueInterface {
            public function enqueue(string $queue, array $payload): bool
            {
                return false;
            }

            public function dequeue(string $queue): ?array
            {
                return null;
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $this->assertFalse((new FallbackJobQueue($failing, $failing))->enqueue('jobs', ['id' => 1]));
    }

    public function testJobsParkedDuringAnOutageAreDrainedAfterRecovery(): void
    {
        $primary = $this->primary(false);
        $secondary = new SyncJobQueue();
        $queue = new FallbackJobQueue($primary, $secondary);

        $queue->enqueue('jobs', ['id' => 'during-outage']);
        $primary->up = true;
        $queue->enqueue('jobs', ['id' => 'after-recovery']);

        // Primary first, then whatever was parked in the fallback: nothing is stranded
        $this->assertSame(['id' => 'after-recovery'], $queue->dequeue('jobs'));
        $this->assertSame(['id' => 'during-outage'], $queue->dequeue('jobs'));
        $this->assertNull($queue->dequeue('jobs'));
    }

    public function testDequeueReadsOnlyTheFallbackWhileThePrimaryIsDown(): void
    {
        $primary = $this->primary(false);
        $secondary = new SyncJobQueue();
        $secondary->enqueue('jobs', ['id' => 7]);
        $queue = new FallbackJobQueue($primary, $secondary);

        $this->assertSame(['id' => 7], $queue->dequeue('jobs'));
        $this->assertNull($queue->dequeue('jobs'));
    }

    public function testAvailabilityIsTrueWhenEitherQueueIsAvailable(): void
    {
        $down = $this->primary(false);
        $up = $this->primary(true);

        $this->assertTrue((new FallbackJobQueue($down, $up))->isAvailable());
        $this->assertTrue((new FallbackJobQueue($up, $down))->isAvailable());
        $this->assertFalse((new FallbackJobQueue($down, $this->primary(false)))->isAvailable());
    }
}
