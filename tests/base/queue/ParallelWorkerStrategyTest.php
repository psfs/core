<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\ParallelWorkerStrategy;

class ParallelWorkerStrategyTest extends TestCase
{
    public function testPlanBuildsOneEntryPerWorker(): void
    {
        $strategy = new ParallelWorkerStrategy();

        $plan = $strategy->plan('notifications', 3, 25);

        $this->assertCount(3, $plan);
        $this->assertSame(['worker' => 1, 'queue' => 'notifications', 'maxJobs' => 25], $plan[0]);
        $this->assertSame(['worker' => 3, 'queue' => 'notifications', 'maxJobs' => 25], $plan[2]);
    }

    public function testPlanRejectsInvalidParameters(): void
    {
        $strategy = new ParallelWorkerStrategy();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workers must be greater than zero.');
        $strategy->plan('notifications', 0, 10);
    }

    public function testPlanRejectsEmptyQueueName(): void
    {
        $strategy = new ParallelWorkerStrategy();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue name cannot be empty.');
        $strategy->plan(' ', 2, 10);
    }
}
