<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\ParallelQueueRunner;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

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

    public function testRunFailsWhenProcOpenSupportIsUnavailable(): void
    {
        $runner = new class extends ParallelQueueRunner {
            protected function canSpawnProcesses(): bool
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('proc_open is required to run queue workers in parallel');

        $runner->run('notifications', 1, 10, 1000, true);
    }

    public function testRunThrowsWhenWorkerSpawnFails(): void
    {
        $runner = new class extends ParallelQueueRunner {
            protected function openProcess(string $command, array $descriptor, array &$pipes, string $cwd)
            {
                return false;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to spawn worker 1');

        $runner->run('notifications', 1, 10, 1000, true);
    }

    public function testRunAggregatesWorkerExitCodeAndFlushesStreams(): void
    {
        $runner = new class extends ParallelQueueRunner {
            private int $spawned = 0;
            public int $sleepCalls = 0;
            private array $statuses = [];
            private array $exitCodes = [];

            protected function openProcess(string $command, array $descriptor, array &$pipes, string $cwd)
            {
                $this->spawned++;
                $process = fopen('php://temp', 'r+');
                $stdout = fopen('php://temp', 'r+');
                $stderr = fopen('php://temp', 'r+');
                fwrite($stdout, "ok-out-{$this->spawned}\n");
                rewind($stdout);
                fwrite($stderr, "ok-err-{$this->spawned}\n");
                rewind($stderr);
                $pipes = [
                    0 => fopen('php://temp', 'r+'),
                    1 => $stdout,
                    2 => $stderr,
                ];
                $this->statuses[(int)$process] = [['running' => true], ['running' => false]];
                $this->exitCodes[(int)$process] = $this->spawned;
                return $process;
            }

            protected function getProcessStatus($process): array
            {
                $id = (int)$process;
                $status = array_shift($this->statuses[$id]);
                return $status ?? ['running' => false];
            }

            protected function closeProcess($process): int
            {
                $id = (int)$process;
                $code = $this->exitCodes[$id] ?? 0;
                $this->closeStream($process);
                return $code;
            }

            protected function sleep(int $microseconds): void
            {
                $this->sleepCalls++;
            }
        };
        $output = new BufferedOutput();

        $exitCode = $runner->run('notifications', 2, 10, 1000, true, $output);
        $printed = $output->fetch();

        $this->assertSame(2, $exitCode);
        $this->assertGreaterThanOrEqual(1, $runner->sleepCalls);
        $this->assertStringContainsString('[queue] spawned worker 1 for notifications', $printed);
        $this->assertStringContainsString('[queue] spawned worker 2 for notifications', $printed);
        $this->assertStringContainsString('[worker:1] ok-out-1', $printed);
        $this->assertStringContainsString('[worker:2:err] ok-err-2', $printed);
    }
}
