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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SwooleConsoleCommandTest extends TestCase
{
    public function testSwooleCommandsAreRegistered(): void
    {
        $console = $this->buildConsole();
        $commands = $console->all();

        $this->assertArrayHasKey('psfs:swoole:start', $commands);
        $this->assertArrayHasKey('psfs:swoole:stop', $commands);
        $this->assertArrayHasKey('psfs:swoole:reload', $commands);
        $this->assertArrayHasKey('psfs:swoole:status', $commands);
        $this->assertArrayHasKey('psfs:swoole:check', $commands);
    }

    public function testSwooleCheckCommandIsDeterministicInCi(): void
    {
        $console = $this->buildConsole();
        $command = $console->find('psfs:swoole:check');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('Runtime check:', $display);
        $this->assertMatchesRegularExpression('/ext-swoole: (OK|MISSING)/', $display);
        $this->assertContains($exitCode, [0, 1], $display);
    }

    public function testStatusCommandWithMissingPidFileReturnsStopped(): void
    {
        $console = $this->buildConsole();
        $pidFile = '/tmp/psfs-swoole-test-' . uniqid('', true) . '.pid';
        @unlink($pidFile);

        $tester = new CommandTester($console->find('psfs:swoole:status'));
        $exitCode = $tester->execute(['--pid-file' => $pidFile]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $exitCode, $display);
        $this->assertStringContainsString('Swoole status: stopped', $display);
    }

    public function testStopAndReloadCommandsWithMissingPidFileAreGraceful(): void
    {
        $console = $this->buildConsole();
        $pidFile = '/tmp/psfs-swoole-test-' . uniqid('', true) . '.pid';
        @unlink($pidFile);

        $stop = new CommandTester($console->find('psfs:swoole:stop'));
        $stopExitCode = $stop->execute(['--pid-file' => $pidFile]);
        $this->assertSame(0, $stopExitCode, $stop->getDisplay());
        $this->assertStringContainsString('Swoole server is not running.', $stop->getDisplay());

        $reload = new CommandTester($console->find('psfs:swoole:reload'));
        $reloadExitCode = $reload->execute(['--pid-file' => $pidFile]);
        $this->assertSame(1, $reloadExitCode, $reload->getDisplay());
        $this->assertStringContainsString('Swoole server is not running.', $reload->getDisplay());
    }

    public function testStartCommandDelegatesToServiceWithoutLaunchingRealServer(): void
    {
        $spy = new StartOptionsSpy();
        $service = $this->buildFakeServiceForStartCommand($spy);
        $console = $this->buildConsole($service);
        $tester = new CommandTester($console->find('psfs:swoole:start'));
        $exitCode = $tester->execute([
            '--host' => '127.0.0.1',
            '--port' => 9510,
            '--workers' => 3,
            '--max-request' => 120,
            '--daemonize' => 1,
            '--pid-file' => '/tmp/spy.pid',
            '--log-file' => '/tmp/spy.log',
        ]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertSame('127.0.0.1', $spy->options['host'] ?? null);
        $this->assertSame(9510, $spy->options['port'] ?? null);
        $this->assertSame(3, $spy->options['workers'] ?? null);
        $this->assertSame(120, $spy->options['max-request'] ?? null);
        $this->assertSame('1', (string)($spy->options['daemonize'] ?? '0'));
        $this->assertSame('/tmp/spy.pid', $spy->options['pid-file'] ?? null);
        $this->assertSame('/tmp/spy.log', $spy->options['log-file'] ?? null);
        $this->assertTrue($spy->called);
    }

    private function buildConsole(?SwooleCommandService $service = null): Application
    {
        $console = new Application();
        if (null !== $service) {
            $swooleCommandService = $service;
        }
        require __DIR__ . '/../../../src/command/SwooleServer.php';
        return $console;
    }

    private function buildFakeServiceForStartCommand(StartOptionsSpy $spy): SwooleCommandService
    {
        $runtime = new class implements SwooleRuntimeInspectorInterface {
            public function hasSwooleSupport(): bool
            {
                return true;
            }
            public function getRuntimeMode(): string
            {
                return 'default';
            }
            public function enableSwooleMode(): void
            {
            }
        };
        $pid = new class implements SwoolePidStoreInterface {
            public function readRunningPid(string $pidFile): ?int
            {
                return null;
            }
            public function writePid(string $pidFile, int $pid): void
            {
            }
            public function removePid(string $pidFile): void
            {
            }
        };
        $signal = new class implements SwooleSignalSenderInterface {
            public function send(int $pid, int $signal): bool
            {
                return true;
            }
        };
        $factory = new class($spy) implements SwooleHttpServerFactoryInterface {
            public function __construct(private StartOptionsSpy $spy)
            {
            }
            public function create(string $host, int $port): SwooleHttpServerInterface
            {
                $this->spy->capturedHost = $host;
                $this->spy->capturedPort = $port;
                return new class implements SwooleHttpServerInterface {
                    public function set(array $settings): void
                    {
                    }
                    public function on(string $event, callable $handler): void
                    {
                    }
                    public function start(): void
                    {
                    }
                };
            }
        };

        return new class($runtime, $pid, $signal, $factory, $spy) extends SwooleCommandService {
            public function __construct(
                SwooleRuntimeInspectorInterface $runtime,
                SwoolePidStoreInterface $pid,
                SwooleSignalSenderInterface $signal,
                SwooleHttpServerFactoryInterface $factory,
                private StartOptionsSpy $spy
            ) {
                parent::__construct($runtime, $pid, $signal, $factory, static fn() => new class {
                    public function handle(object $request, object $response): void
                    {
                    }
                });
            }

            public function start(array $options, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $this->spy->called = true;
                $this->spy->options = $options;
                return 0;
            }
        };
    }
}

class StartOptionsSpy
{
    public bool $called = false;
    public array $options = [];
    public ?string $capturedHost = null;
    public ?int $capturedPort = null;
}
