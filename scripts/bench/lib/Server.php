<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * A `php -S` process serving the sandbox with the opcache and session settings of one cell
 */
final class Server
{
    /** @var resource|null */
    private $process = null;

    public function __construct(private readonly string $sandbox)
    {
    }

    /**
     * @return int the port the server listens on
     */
    public function start(string $opcache, string $redis, string $statsFile): int
    {
        $port = $this->freePort();
        $log = $this->sandbox . '/server.log';
        $command = array_merge([PHP_BINARY], $this->flags($opcache, $redis), ['-S', '127.0.0.1:' . $port, '-t', $this->sandbox . '/html']);
        $this->process = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $this->sandbox,
            $this->environment($statsFile)
        );
        if (!is_resource($this->process)) {
            throw new \RuntimeException('Unable to start php -S');
        }
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
     * @return string[]
     */
    private function flags(string $opcache, string $redis): array
    {
        $flags = ['-d', 'xdebug.mode=off', '-d', 'auto_prepend_file=' . $this->sandbox . '/bench-prepend.php'];
        $flags = array_merge($flags, $this->opcacheFlags($opcache));
        if ('off' === $redis) {
            // 'sessions' and 'on' keep the session handler of docker/php.ini (Redis)
            $flags = array_merge($flags, ['-d', 'session.save_handler=files', '-d', 'session.save_path=' . $this->sandbox . '/sessions']);
        }
        return $flags;
    }

    /**
     * @return string[]
     */
    private function opcacheFlags(string $mode): array
    {
        if ('off' === $mode) {
            return ['-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0'];
        }
        $flags = [
            '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0',
            '-d', 'opcache.memory_consumption=128', '-d', 'opcache.max_accelerated_files=10000',
        ];
        $jit = 'jit' === $mode ? ['-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=100M'] : ['-d', 'opcache.jit=off'];
        return array_merge($flags, $jit);
    }

    /**
     * @return array<string,string>
     */
    private function environment(string $statsFile): array
    {
        return array_merge(getenv(), [
            'BENCH_STATS_FILE' => $statsFile,
            'PSFS_REDIS_HOST' => getenv('PSFS_REDIS_HOST') ?: 'redis',
            'PSFS_LOG_OUTPUT' => 'file', 'PSFS_LOG_FORMAT' => 'line', 'PSFS_LOG_LEVEL' => '',
            // The generated API module has no api.secret and AuthApi denies access without one: the benchmark
            // measures the framework, not the token check, so it uses the (deprecated) compatibility switch
            'PSFS_API_AUTH_ALLOW_UNCONFIGURED' => 'true',
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
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if (false !== $socket) {
                fclose($socket);
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('php -S did not start; see ' . $log);
    }
}
