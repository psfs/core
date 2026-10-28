<?php

namespace PSFS\tests\base\command\console;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\console\QueueDispatchCommand;
use PSFS\base\command\console\QueueRunParallelCommand;
use PSFS\base\command\console\QueueWorkCommand;
use PSFS\base\queue\JobQueueInterface;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\ParallelQueueRunner;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueWorker;
use PSFS\base\queue\SyncJobQueue;
use PSFS\tests\base\queue\fixtures\TestQueueJob;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class QueueCommandsTest extends TestCase
{
    public function testDispatchRequiresACode(): void
    {
        $tester = new CommandTester(new QueueDispatchCommand());

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Option --code is required', $tester->getDisplay());
    }

    public function testDispatchRejectsAPayloadThatIsNotJson(): void
    {
        $tester = new CommandTester(new QueueDispatchCommand());

        $this->assertSame(1, $tester->execute(['--code' => 'notifications', '--payload' => 'not json']));
        $this->assertStringContainsString('must be a valid JSON', $tester->getDisplay());
    }

    public function testDispatchEnqueuesTheJobOnTheCodeQueueByDefault(): void
    {
        $queue = new SyncJobQueue();
        $tester = new CommandTester(new DispatchCommandWithQueue($queue));

        $exitCode = $tester->execute(['--code' => TestQueueJob::code(), '--payload' => '{"id":7}']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('Queued job ' . TestQueueJob::code() . ' on ' . TestQueueJob::code(), $tester->getDisplay());
        $this->assertSame(['id' => 7], $queue->dequeue(TestQueueJob::code())['payload']);
    }

    public function testDispatchReportsAQueueThatRefusesTheJob(): void
    {
        $tester = new CommandTester(new DispatchCommandWithQueue(new RefusingJobQueue()));

        $exitCode = $tester->execute(['--code' => TestQueueJob::code(), '--queue' => 'mail']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unable to enqueue job ' . TestQueueJob::code() . ' on queue mail', $tester->getDisplay());
    }

    public function testWorkRequiresAQueue(): void
    {
        $tester = new CommandTester(new QueueWorkCommand());

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Option --queue is required', $tester->getDisplay());
    }

    public function testWorkStopsWhenTheQueueIsEmptyAndReportsTheProcessedJobs(): void
    {
        $tester = new CommandTester(new WorkCommandWithQueue(new SyncJobQueue()));

        $exitCode = $tester->execute(['--queue' => 'empty', '--stop-when-empty' => '1']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('Worker completed. Processed jobs: 0', $tester->getDisplay());
    }

    public function testRunParallelRequiresAQueue(): void
    {
        $tester = new CommandTester(new QueueRunParallelCommand());

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Option --queue is required', $tester->getDisplay());
    }

    public function testRunParallelPassesTheNormalisedOptionsToTheRunner(): void
    {
        $runner = new RecordingParallelRunner();
        $tester = new CommandTester(new RunParallelCommandWithRunner($runner));

        $exitCode = $tester->execute(['--queue' => 'mail', '--workers' => '0', '--max-jobs' => '-3', '--idle-sleep' => '5']);

        $this->assertSame(7, $exitCode);
        $this->assertSame(['mail', 1, 0, 1000, true], $runner->arguments);
    }
}

final class DispatchCommandWithQueue extends QueueDispatchCommand
{
    public function __construct(private readonly JobQueueInterface $queue)
    {
        parent::__construct();
    }

    protected function dispatcher(): QueueDispatcher
    {
        return new QueueDispatcher($this->queue, new JobRegistry([TestQueueJob::class], []));
    }
}

final class WorkCommandWithQueue extends QueueWorkCommand
{
    public function __construct(private readonly JobQueueInterface $queue)
    {
        parent::__construct();
    }

    protected function worker(): QueueWorker
    {
        return new QueueWorker(new QueueDispatcher($this->queue, new JobRegistry([TestQueueJob::class], [])));
    }
}

final class RunParallelCommandWithRunner extends QueueRunParallelCommand
{
    public function __construct(private readonly ParallelQueueRunner $runner)
    {
        parent::__construct();
    }

    protected function runner(): ParallelQueueRunner
    {
        return $this->runner;
    }
}

final class RefusingJobQueue implements JobQueueInterface
{
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
}

final class RecordingParallelRunner extends ParallelQueueRunner
{
    /** @var array<int, mixed> */
    public array $arguments = [];

    public function run(
        string $queueName,
        int $workers,
        int $maxJobs,
        int $idleSleepUs,
        bool $stopWhenEmpty,
        ?OutputInterface $output = null
    ): int {
        $this->arguments = [$queueName, $workers, $maxJobs, $idleSleepUs, $stopWhenEmpty];
        return 7;
    }
}
