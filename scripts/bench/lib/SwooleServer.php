<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The PSFS Swoole runtime (`psfs:swoole:start`) serving the sandbox, with access to the memory of its workers
 */
final class SwooleServer
{
    /** @var resource|null */
    private $process = null;
    private int $master = 0;

    public function __construct(private readonly string $sandbox)
    {
    }

    /**
     * @return int the port the server listens on
     */
    public function start(int $workers, int $maxRequest, string $opcache): int
    {
        $port = $this->freePort();
        $log = $this->sandbox . '/swoole-server.log';
        $command = array_merge(
            [PHP_BINARY, '-d', 'xdebug.mode=off'],
            $this->opcacheFlags($opcache),
            ['src/bin/psfs', 'psfs:swoole:start', '--host=127.0.0.1', '--port=' . $port, '--workers=' . $workers, '--max-request=' . $maxRequest, '--pid-file=' . $this->sandbox . '/swoole.pid', '--log-file=' . $log]
        );
        $this->process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $this->sandbox, $this->environment());
        if (!is_resource($this->process)) {
            throw new \RuntimeException('Unable to start the Swoole runtime');
        }
        $this->master = (int)proc_get_status($this->process)['pid'];
        $this->waitUntilListening($port, $log);
        return $port;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
    }

    /**
     * @return array<int,int> resident memory in KB of every worker process, by pid
     */
    public function workerMemory(): array
    {
        $memory = [];
        foreach ($this->workers() as $pid) {
            $status = (string)@file_get_contents('/proc/' . $pid . '/status');
            if (1 === preg_match('/VmRSS:\s+(\d+)/', $status, $matches)) {
                $memory[$pid] = (int)$matches[1];
            }
        }
        return $memory;
    }

    /**
     * Workers are the processes without children below the master (a manager, when there is one, owns them)
     *
     * @return int[]
     */
    private function workers(): array
    {
        $children = [];
        foreach (glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $directory) {
            $stat = (string)@file_get_contents($directory . '/stat');
            if (1 === preg_match('/^(\d+) \(.*\) \S (\d+)/', $stat, $matches)) {
                $children[(int)$matches[2]][] = (int)$matches[1];
            }
        }
        // with a single worker Swoole runs it inside the master process
        return $this->leaves($children, $this->master) ?: [$this->master];
    }

    /**
     * @param array<int,int[]> $children
     * @return int[]
     */
    private function leaves(array $children, int $pid): array
    {
        $leaves = [];
        foreach ($children[$pid] ?? [] as $child) {
            array_push($leaves, ...(isset($children[$child]) ? $this->leaves($children, $child) : [$child]));
        }
        return $leaves;
    }

    /**
     * @return string[]
     */
    private function opcacheFlags(string $mode): array
    {
        if ('off' === $mode) {
            return ['-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0'];
        }
        return ['-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.memory_consumption=128', '-d', 'opcache.jit=off'];
    }

    /**
     * @return array<string,string>
     */
    private function environment(): array
    {
        return array_merge(getenv(), [
            'PSFS_REDIS_HOST' => getenv('PSFS_REDIS_HOST') ?: 'redis',
            'PSFS_LOG_OUTPUT' => 'file', 'PSFS_LOG_FORMAT' => 'line', 'PSFS_LOG_LEVEL' => '',
            'PSFS_API_AUTH_ALLOW_UNCONFIGURED' => 'true',
            'UI_DEV_UPSTREAM' => '', 'ADMIN_UI_DEV_UPSTREAM' => '',
        ]);
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)explode(':', (string)stream_socket_get_name($socket, false))[1];
        fclose($socket);
        return $port;
    }

    private function waitUntilListening(int $port, string $log): void
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if (false !== $socket) {
                fclose($socket);
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('The Swoole runtime did not start; see ' . $log);
    }
}
