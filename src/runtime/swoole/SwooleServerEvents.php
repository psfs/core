<?php

namespace PSFS\runtime\swoole;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * The callbacks of one Swoole server: each event is a named method that the service registers with
 * `$events->onStart(...)`, so the lifecycle reads from here and not from closures built while starting
 */
class SwooleServerEvents
{
    public function __construct(
        private readonly SwoolePidStoreInterface $pidStore,
        private readonly OutputInterface $output,
        private readonly string $pidFile,
        private readonly string $host,
        private readonly int $port,
        private readonly object $handler,
        private readonly object $webSocketBridge
    ) {
    }

    public function onStart(object $server): void
    {
        $masterPid = isset($server->master_pid) ? (int)$server->master_pid : 0;
        if ($masterPid > 0) {
            $this->pidStore->writePid($this->pidFile, $masterPid);
        }
        $this->output->writeln(
            sprintf('PSFS Swoole started on %s:%d (master pid: %d)', $this->host, $this->port, $masterPid)
        );
    }

    public function onShutdown(): void
    {
        $this->pidStore->removePid($this->pidFile);
    }

    public function onRequest(object $request, object $response): void
    {
        $this->handler->handle($request, $response);
    }

    public function onOpen(object $server, object $request): void
    {
        $this->webSocketBridge->open($server, $request);
    }

    public function onMessage(object $server, object $frame): void
    {
        $this->webSocketBridge->message($server, $frame);
    }

    public function onClose(object $server, int $fd): void
    {
        $this->webSocketBridge->close($server, $fd);
    }
}
