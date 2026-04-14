<?php

namespace PSFS\runtime\swoole;

use LogicException;
use PSFS\base\runtime\RuntimeMode;
use Symfony\Component\Console\Output\OutputInterface;

interface SwooleRuntimeInspectorInterface
{
    public function hasSwooleSupport(): bool;

    public function getRuntimeMode(): string;

    public function enableSwooleMode(): void;
}

interface SwoolePidStoreInterface
{
    public function readRunningPid(string $pidFile): ?int;

    public function writePid(string $pidFile, int $pid): void;

    public function removePid(string $pidFile): void;
}

interface SwooleSignalSenderInterface
{
    public function send(int $pid, int $signal): bool;
}

interface SwooleHttpServerInterface
{
    public function set(array $settings): void;

    public function on(string $event, callable $handler): void;

    public function start(): void;
}

interface SwooleHttpServerFactoryInterface
{
    public function create(string $host, int $port): SwooleHttpServerInterface;
}

class NativeSwooleRuntimeInspector implements SwooleRuntimeInspectorInterface
{
    public function hasSwooleSupport(): bool
    {
        return extension_loaded('swoole') && class_exists('Swoole\\Http\\Server');
    }

    public function getRuntimeMode(): string
    {
        return RuntimeMode::getCurrentMode() ?: 'default';
    }

    public function enableSwooleMode(): void
    {
        RuntimeMode::enableSwoole();
    }
}

class NativeSwoolePidStore implements SwoolePidStoreInterface
{
    public function readRunningPid(string $pidFile): ?int
    {
        return SwoolePidManager::readRunningPid($pidFile);
    }

    public function writePid(string $pidFile, int $pid): void
    {
        SwoolePidManager::writePid($pidFile, $pid);
    }

    public function removePid(string $pidFile): void
    {
        SwoolePidManager::removePid($pidFile);
    }
}

class NativeSwooleSignalSender implements SwooleSignalSenderInterface
{
    public function send(int $pid, int $signal): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return posix_kill($pid, $signal);
        }
        $signalName = $signal === 10 ? 'USR1' : 'TERM';
        $result = exec('kill -' . $signalName . ' ' . (int)$pid . ' 2>/dev/null', $output, $code);
        return $result !== false && $code === 0;
    }
}

class NativeSwooleHttpServerAdapter implements SwooleHttpServerInterface
{
    public function __construct(private object $server)
    {
    }

    public function set(array $settings): void
    {
        $this->server->set($settings);
    }

    public function on(string $event, callable $handler): void
    {
        $this->server->on($event, $handler);
    }

    public function start(): void
    {
        $this->server->start();
    }
}

class NativeSwooleHttpServerFactory implements SwooleHttpServerFactoryInterface
{
    public function create(string $host, int $port): SwooleHttpServerInterface
    {
        $serverClass = 'Swoole\\Http\\Server';
        if (!class_exists($serverClass)) {
            throw new LogicException('Swoole Http Server class is not available');
        }
        $server = new $serverClass($host, $port);
        return new NativeSwooleHttpServerAdapter($server);
    }
}

class SwooleCommandService
{
    /** @var callable */
    private $handlerFactory;

    public function __construct(
        private SwooleRuntimeInspectorInterface $runtimeInspector = new NativeSwooleRuntimeInspector(),
        private SwoolePidStoreInterface $pidStore = new NativeSwoolePidStore(),
        private SwooleSignalSenderInterface $signalSender = new NativeSwooleSignalSender(),
        private SwooleHttpServerFactoryInterface $serverFactory = new NativeSwooleHttpServerFactory(),
        ?callable $handlerFactory = null
    ) {
        $this->handlerFactory = $handlerFactory ?? static fn() => new SwooleRequestHandler();
    }

    public function start(array $options, OutputInterface $output): int
    {
        if (!$this->runtimeInspector->hasSwooleSupport()) {
            $output->writeln('<error>Swoole extension is required. Install/enable ext-swoole first.</error>');
            return 1;
        }

        $host = (string)($options['host'] ?? '0.0.0.0');
        $port = max(1, (int)($options['port'] ?? 8080));
        $workers = max(1, (int)($options['workers'] ?? 2));
        $maxRequest = max(1, (int)($options['max-request'] ?? 1000));
        $daemonize = in_array((string)($options['daemonize'] ?? 0), ['1', 'true', 'yes'], true);
        $pidFile = (string)($options['pid-file'] ?? '/tmp/psfs-swoole.pid');
        $logFile = (string)($options['log-file'] ?? '/tmp/psfs-swoole.log');

        $runningPid = $this->pidStore->readRunningPid($pidFile);
        if (null !== $runningPid) {
            $output->writeln(sprintf('<error>Swoole server is already running (PID %d)</error>', $runningPid));
            return 1;
        }

        $this->runtimeInspector->enableSwooleMode();
        $server = $this->serverFactory->create($host, $port);
        $server->set([
            'worker_num' => $workers,
            'max_request' => $maxRequest,
            'daemonize' => $daemonize,
            'log_file' => $logFile,
        ]);

        $handler = ($this->handlerFactory)();

        $server->on('Start', function ($serverInstance) use ($pidFile, $output, $host, $port): void {
            $masterPid = isset($serverInstance->master_pid) ? (int)$serverInstance->master_pid : 0;
            if ($masterPid > 0) {
                $this->pidStore->writePid($pidFile, $masterPid);
            }
            $output->writeln(
                sprintf('PSFS Swoole started on %s:%d (master pid: %d)', $host, $port, $masterPid)
            );
        });

        $server->on('Shutdown', function () use ($pidFile): void {
            $this->pidStore->removePid($pidFile);
        });

        $server->on('request', function ($request, $response) use ($handler): void {
            $handler->handle($request, $response);
        });

        $output->writeln(
            sprintf(
                'Starting PSFS Swoole runtime with workers=%d max_request=%d daemonize=%s',
                $workers,
                $maxRequest,
                $daemonize ? '1' : '0'
            )
        );
        $server->start();
        return 0;
    }

    public function stop(array $options, OutputInterface $output): int
    {
        $pidFile = (string)($options['pid-file'] ?? '/tmp/psfs-swoole.pid');
        $pid = $this->pidStore->readRunningPid($pidFile);
        if (null === $pid) {
            $output->writeln('<info>Swoole server is not running.</info>');
            return 0;
        }
        if (!$this->signalSender->send($pid, 15)) {
            $output->writeln(sprintf('<error>Unable to stop Swoole process PID %d</error>', $pid));
            return 1;
        }
        $this->pidStore->removePid($pidFile);
        $output->writeln(sprintf('Sent SIGTERM to PID %d', $pid));
        return 0;
    }

    public function reload(array $options, OutputInterface $output): int
    {
        $pidFile = (string)($options['pid-file'] ?? '/tmp/psfs-swoole.pid');
        $pid = $this->pidStore->readRunningPid($pidFile);
        if (null === $pid) {
            $output->writeln('<error>Swoole server is not running.</error>');
            return 1;
        }
        if (!$this->signalSender->send($pid, 10)) {
            $output->writeln(sprintf('<error>Unable to reload Swoole process PID %d</error>', $pid));
            return 1;
        }
        $output->writeln(sprintf('Sent SIGUSR1 to PID %d', $pid));
        return 0;
    }

    public function status(array $options, OutputInterface $output): int
    {
        $pidFile = (string)($options['pid-file'] ?? '/tmp/psfs-swoole.pid');
        $pid = $this->pidStore->readRunningPid($pidFile);
        if (null === $pid) {
            $output->writeln('Swoole status: stopped');
            return 1;
        }
        $output->writeln(sprintf('Swoole status: running (PID %d)', $pid));
        return 0;
    }

    public function check(OutputInterface $output): int
    {
        $hasExtension = $this->runtimeInspector->hasSwooleSupport();
        $output->writeln('Runtime check:');
        $output->writeln(sprintf('- ext-swoole: %s', $hasExtension ? 'OK' : 'MISSING'));
        $output->writeln(sprintf('- runtime mode: %s', $this->runtimeInspector->getRuntimeMode()));
        return $hasExtension ? 0 : 1;
    }
}
