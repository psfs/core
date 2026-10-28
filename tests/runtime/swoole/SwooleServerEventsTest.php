<?php

namespace PSFS\tests\runtime\swoole;

require_once __DIR__ . '/../../../src/runtime/swoole/SwooleCommandService.php';

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwoolePidStoreInterface;
use PSFS\runtime\swoole\SwooleServerEvents;
use Symfony\Component\Console\Output\BufferedOutput;

class SwooleServerEventsTest extends TestCase
{
    public function testStartingWritesThePidOfTheMasterAndAnnouncesTheAddress(): void
    {
        $pids = new RecordingPidStore();
        $output = new BufferedOutput();
        $events = new SwooleServerEvents($pids, $output, '/tmp/x.pid', '0.0.0.0', 8080, new RecordingRuntimeHandler(), new RecordingBridge());

        $events->onStart((object)['master_pid' => 4321]);

        $this->assertSame([['write', '/tmp/x.pid', 4321]], $pids->calls);
        $this->assertStringContainsString('PSFS Swoole started on 0.0.0.0:8080 (master pid: 4321)', $output->fetch());
    }

    public function testStartingWithoutAMasterPidDoesNotWriteTheFile(): void
    {
        $pids = new RecordingPidStore();
        $events = new SwooleServerEvents($pids, new BufferedOutput(), '/tmp/x.pid', 'h', 1, new RecordingRuntimeHandler(), new RecordingBridge());

        $events->onStart(new \stdClass());

        $this->assertSame([], $pids->calls);
    }

    public function testShuttingDownRemovesThePidFile(): void
    {
        $pids = new RecordingPidStore();
        $events = new SwooleServerEvents($pids, new BufferedOutput(), '/tmp/x.pid', 'h', 1, new RecordingRuntimeHandler(), new RecordingBridge());

        $events->onShutdown();

        $this->assertSame([['remove', '/tmp/x.pid', null]], $pids->calls);
    }

    public function testRequestsGoToTheHandlerAndWebSocketEventsToTheBridge(): void
    {
        $handler = new RecordingRuntimeHandler();
        $bridge = new RecordingBridge();
        $events = new SwooleServerEvents(new RecordingPidStore(), new BufferedOutput(), 'p', 'h', 1, $handler, $bridge);
        $request = new \stdClass();
        $response = new \stdClass();
        $server = new \stdClass();
        $frame = new \stdClass();

        $events->onRequest($request, $response);
        $events->onOpen($server, $request);
        $events->onMessage($server, $frame);
        $events->onClose($server, 7);

        $this->assertSame([[$request, $response]], $handler->handled);
        $this->assertSame([['open', $server, $request], ['message', $server, $frame], ['close', $server, 7]], $bridge->events);
    }
}

final class RecordingPidStore implements SwoolePidStoreInterface
{
    /** @var list<array{0:string,1:string,2:?int}> */
    public array $calls = [];

    public function readRunningPid(string $pidFile): ?int
    {
        return null;
    }

    public function writePid(string $pidFile, int $pid): void
    {
        $this->calls[] = ['write', $pidFile, $pid];
    }

    public function removePid(string $pidFile): void
    {
        $this->calls[] = ['remove', $pidFile, null];
    }
}

final class RecordingRuntimeHandler
{
    /** @var list<array{0:object,1:object}> */
    public array $handled = [];

    public function handle(object $request, object $response): void
    {
        $this->handled[] = [$request, $response];
    }
}

final class RecordingBridge
{
    /** @var list<array<int,mixed>> */
    public array $events = [];

    public function open(object $server, object $request): void
    {
        $this->events[] = ['open', $server, $request];
    }

    public function message(object $server, object $frame): void
    {
        $this->events[] = ['message', $server, $frame];
    }

    public function close(object $server, int $fd): void
    {
        $this->events[] = ['close', $server, $fd];
    }
}
