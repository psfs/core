<?php

namespace PSFS\tests\runtime\swoole;

require_once __DIR__ . '/../../../src/runtime/swoole/SwooleCommandService.php';

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwooleCommandService;
use PSFS\runtime\swoole\SwooleHttpServerFactoryInterface;
use PSFS\runtime\swoole\SwooleHttpServerInterface;
use PSFS\runtime\swoole\SwoolePidStoreInterface;
use PSFS\runtime\swoole\SwooleRuntimeInspectorInterface;
use PSFS\runtime\swoole\SwooleSignalSenderInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class SwooleCommandServiceTest extends TestCase
{
    public function testStartFailsWhenSwooleSupportIsMissing(): void
    {
        $runtime = new RuntimeInspectorDouble(false, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => new HandlerDouble());
        $output = new BufferedOutput();

        $exitCode = $service->start([], $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Swoole extension is required', $output->fetch());
        $this->assertFalse($runtime->enableCalled);
    }

    public function testStartFailsWhenServerAlreadyRunning(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $pidStore->runningPid = 3456;
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => new HandlerDouble());
        $output = new BufferedOutput();

        $exitCode = $service->start([], $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already running (PID 3456)', $output->fetch());
        $this->assertFalse($runtime->enableCalled);
        $this->assertNull($factory->server);
    }

    public function testStartConfiguresServerAndWritesPidOnStartEvent(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $handler = new HandlerDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => $handler);
        $output = new BufferedOutput();

        $exitCode = $service->start([
            'host' => '127.0.0.1',
            'port' => 9501,
            'workers' => 4,
            'max-request' => 250,
            'daemonize' => 'yes',
            'pid-file' => '/tmp/swoole-test.pid',
            'log-file' => '/tmp/swoole-test.log',
        ], $output);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($runtime->enableCalled);
        $this->assertNotNull($factory->server);
        $this->assertSame([
            'worker_num' => 4,
            'max_request' => 250,
            'daemonize' => true,
            'log_file' => '/tmp/swoole-test.log',
        ], $factory->server->settings);
        $this->assertSame('/tmp/swoole-test.pid', $pidStore->lastWrittenPidFile);
        $this->assertSame(9876, $pidStore->lastWrittenPid);
        $text = $output->fetch();
        $this->assertStringContainsString('Starting PSFS Swoole runtime with workers=4 max_request=250 daemonize=1', $text);
        $this->assertStringContainsString('PSFS Swoole started on 127.0.0.1:9501 (master pid: 9876)', $text);
        $factory->server->fireShutdown();
        $this->assertSame('/tmp/swoole-test.pid', $pidStore->lastRemovedPidFile);
        $this->assertSame(0, $handler->handledRequests);
    }

    public function testStartRegistersRequestHandler(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $handler = new HandlerDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => $handler);
        $output = new BufferedOutput();

        $service->start([], $output);
        $this->assertNotNull($factory->server);
        $factory->server->fireRequest(new \stdClass(), new \stdClass());
        $this->assertSame(1, $handler->handledRequests);
    }

    public function testStopHandlesAllBranches(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => new HandlerDouble());

        $output = new BufferedOutput();
        $exitCode = $service->stop(['pid-file' => '/tmp/missing.pid'], $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('not running', $output->fetch());

        $pidStore->runningPid = 1234;
        $signal->nextResult = false;
        $output = new BufferedOutput();
        $exitCode = $service->stop(['pid-file' => '/tmp/running.pid'], $output);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unable to stop Swoole process PID 1234', $output->fetch());

        $signal->nextResult = true;
        $output = new BufferedOutput();
        $exitCode = $service->stop(['pid-file' => '/tmp/running.pid'], $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Sent SIGTERM to PID 1234', $output->fetch());
        $this->assertSame('/tmp/running.pid', $pidStore->lastRemovedPidFile);
    }

    public function testReloadHandlesAllBranches(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => new HandlerDouble());

        $output = new BufferedOutput();
        $exitCode = $service->reload(['pid-file' => '/tmp/missing.pid'], $output);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not running', $output->fetch());

        $pidStore->runningPid = 2222;
        $signal->nextResult = false;
        $output = new BufferedOutput();
        $exitCode = $service->reload(['pid-file' => '/tmp/running.pid'], $output);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unable to reload Swoole process PID 2222', $output->fetch());

        $signal->nextResult = true;
        $output = new BufferedOutput();
        $exitCode = $service->reload(['pid-file' => '/tmp/running.pid'], $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Sent SIGUSR1 to PID 2222', $output->fetch());
        $this->assertSame([2222, 10], $signal->lastCall);
    }

    public function testStatusCommandReturnsStoppedAndRunningStates(): void
    {
        $runtime = new RuntimeInspectorDouble(true, 'default');
        $pidStore = new PidStoreDouble();
        $signal = new SignalSenderDouble();
        $factory = new ServerFactoryDouble();
        $service = new SwooleCommandService($runtime, $pidStore, $signal, $factory, static fn() => new HandlerDouble());

        $output = new BufferedOutput();
        $exitCode = $service->status(['pid-file' => '/tmp/missing.pid'], $output);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Swoole status: stopped', $output->fetch());

        $pidStore->runningPid = 7777;
        $output = new BufferedOutput();
        $exitCode = $service->status(['pid-file' => '/tmp/running.pid'], $output);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Swoole status: running (PID 7777)', $output->fetch());
    }

    public function testCheckCommandReportsModeAndExtensionState(): void
    {
        $runtimeMissing = new RuntimeInspectorDouble(false, 'default');
        $serviceMissing = new SwooleCommandService($runtimeMissing, new PidStoreDouble(), new SignalSenderDouble(), new ServerFactoryDouble(), static fn() => new HandlerDouble());
        $output = new BufferedOutput();
        $exitCode = $serviceMissing->check($output);
        $this->assertSame(1, $exitCode);
        $text = $output->fetch();
        $this->assertStringContainsString('- ext-swoole: MISSING', $text);
        $this->assertStringContainsString('- runtime mode: default', $text);

        $runtimeOk = new RuntimeInspectorDouble(true, 'swoole');
        $serviceOk = new SwooleCommandService($runtimeOk, new PidStoreDouble(), new SignalSenderDouble(), new ServerFactoryDouble(), static fn() => new HandlerDouble());
        $output = new BufferedOutput();
        $exitCode = $serviceOk->check($output);
        $this->assertSame(0, $exitCode);
        $text = $output->fetch();
        $this->assertStringContainsString('- ext-swoole: OK', $text);
        $this->assertStringContainsString('- runtime mode: swoole', $text);
    }
}

class RuntimeInspectorDouble implements SwooleRuntimeInspectorInterface
{
    public bool $enableCalled = false;

    public function __construct(private bool $hasSupport, private string $mode)
    {
    }

    public function hasSwooleSupport(): bool
    {
        return $this->hasSupport;
    }

    public function getRuntimeMode(): string
    {
        return $this->mode;
    }

    public function enableSwooleMode(): void
    {
        $this->enableCalled = true;
        $this->mode = 'swoole';
    }
}

class PidStoreDouble implements SwoolePidStoreInterface
{
    public ?int $runningPid = null;
    public ?string $lastWrittenPidFile = null;
    public ?int $lastWrittenPid = null;
    public ?string $lastRemovedPidFile = null;

    public function readRunningPid(string $pidFile): ?int
    {
        return $this->runningPid;
    }

    public function writePid(string $pidFile, int $pid): void
    {
        $this->lastWrittenPidFile = $pidFile;
        $this->lastWrittenPid = $pid;
    }

    public function removePid(string $pidFile): void
    {
        $this->lastRemovedPidFile = $pidFile;
    }
}

class SignalSenderDouble implements SwooleSignalSenderInterface
{
    public bool $nextResult = true;
    public ?array $lastCall = null;

    public function send(int $pid, int $signal): bool
    {
        $this->lastCall = [$pid, $signal];
        return $this->nextResult;
    }
}

class ServerFactoryDouble implements SwooleHttpServerFactoryInterface
{
    public ?ServerDouble $server = null;

    public function create(string $host, int $port): SwooleHttpServerInterface
    {
        $this->server = new ServerDouble($host, $port);
        return $this->server;
    }
}

class ServerDouble implements SwooleHttpServerInterface
{
    public array $settings = [];
    /** @var array<string, callable> */
    private array $events = [];

    public function __construct(private string $host, private int $port)
    {
    }

    public function set(array $settings): void
    {
        $this->settings = $settings;
    }

    public function on(string $event, callable $handler): void
    {
        $this->events[$event] = $handler;
    }

    public function start(): void
    {
        if (isset($this->events['Start'])) {
            ($this->events['Start'])((object)['master_pid' => 9876]);
        }
    }

    public function fireRequest(object $request, object $response): void
    {
        if (isset($this->events['request'])) {
            ($this->events['request'])($request, $response);
        }
    }

    public function fireShutdown(): void
    {
        if (isset($this->events['Shutdown'])) {
            ($this->events['Shutdown'])();
        }
    }
}

class HandlerDouble
{
    public int $handledRequests = 0;

    public function handle(object $request, object $response): void
    {
        $this->handledRequests++;
    }
}
