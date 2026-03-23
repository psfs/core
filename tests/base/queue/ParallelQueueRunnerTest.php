<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\ParallelQueueRunner;
use RuntimeException;

class ParallelQueueRunnerTest extends TestCase
{
    public function testRunRejectsWorkerCountLowerThanOne(): void
    {
        $runner = new ParallelQueueRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Workers must be >= 1');

        $runner->run('notifications', 0, 10, 1000, true);
    }

    public function testBuildWorkerCommandIncludesAllArguments(): void
    {
        $runner = new ParallelQueueRunner();

        $command = $runner->buildWorkerCommand('queue with spaces', 25, 200000, true);

        $this->assertStringContainsString(escapeshellarg(PHP_BINARY), $command);
        $this->assertStringContainsString(escapeshellarg(SOURCE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'psfs'), $command);
        $this->assertStringContainsString('psfs:queue:work', $command);
        $this->assertStringContainsString("--queue='queue with spaces'", $command);
        $this->assertStringContainsString("--max-jobs='25'", $command);
        $this->assertStringContainsString("--idle-sleep='200000'", $command);
        $this->assertStringContainsString("--stop-when-empty='1'", $command);
    }

    public function testBuildWorkerCommandEncodesStopWhenEmptyFalseAsZero(): void
    {
        $runner = new ParallelQueueRunner();

        $command = $runner->buildWorkerCommand('notifications', 1, 1000, false);

        $this->assertStringContainsString("--stop-when-empty='0'", $command);
    }
}
