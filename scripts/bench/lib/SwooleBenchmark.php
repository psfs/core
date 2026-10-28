<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Compares the Swoole runtime with `php -S` on the same sandbox and watches the memory of its workers: a worker
 * lives for thousands of requests, so anything a request leaves behind accumulates.
 */
final class SwooleBenchmark
{
    private const SCENARIOS = ['page', 'api-get'];
    private ?Sandbox $sandbox = null;
    private ?Services $services = null;
    private ?SwooleServer $swoole = null;
    private ?Server $builtIn = null;

    /**
     * @param int[] $concurrency
     */
    public function __construct(
        private readonly int $workers,
        private readonly int $requests,
        private readonly array $concurrency,
        private readonly int $leakRequests
    ) {
    }

    public function run(): string
    {
        $options = Options::fromArguments(['--scenarios=' . implode(',', self::SCENARIOS), '--modules=2']);
        try {
            $this->prepare($options);
            return $this->report($this->measureAll());
        } finally {
            $this->cleanup();
        }
    }

    private function prepare(Options $options): void
    {
        $this->services = Services::connect($options);
        $this->sandbox = new Sandbox(dirname(__DIR__, 3), $options);
        $this->sandbox->build();
        $this->sandbox->useModules(2);
        (new Dataset($this->services->pdo(), $this->sandbox->database()))->useRows(1000);
        $this->sandbox->writeConfig(['debug' => false, 'psfs.redis' => false]);
        $this->sandbox->resetCaches();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function measureAll(): array
    {
        $rows = [];
        foreach (self::SCENARIOS as $name) {
            $scenario = Scenarios::get($name);
            fprintf(STDERR, "[%s] php -S ... ", $name);
            $rows[] = $this->builtInRow($name, $scenario);
            fprintf(STDERR, "p50 %.2f ms\n", $rows[array_key_last($rows)]['p50']);
            foreach ($this->concurrency as $level) {
                fprintf(STDERR, "[%s] swoole c=%d ... ", $name, $level);
                $rows[] = $this->swooleRow($name, $scenario, $level);
                fprintf(STDERR, "%.0f req/s, p50 %.2f ms\n", $rows[array_key_last($rows)]['rps'], $rows[array_key_last($rows)]['p50']);
            }
        }
        return $rows;
    }

    /**
     * @param array{path:string,marker:string} $scenario
     * @return array<string,mixed>
     */
    private function builtInRow(string $name, array $scenario): array
    {
        $this->builtIn = new Server($this->sandbox->path());
        $port = $this->builtIn->start('on', 'off', $this->sandbox->path() . '/builtin-stats.log');
        try {
            $probe = new Probe('http://127.0.0.1:' . $port . $scenario['path'], $scenario['marker']);
            $probe->sample(20);
            $times = $probe->sample($this->requests);
        } finally {
            $this->builtIn->stop();
        }
        $p50 = Stats::percentile($times, 50);
        return ['scenario' => $name, 'runtime' => 'php -S', 'concurrency' => 1, 'rps' => 1000 / $p50, 'p50' => $p50, 'p95' => Stats::percentile($times, 95), 'growth' => null];
    }

    /**
     * @param array{path:string,marker:string} $scenario
     * @return array<string,mixed>
     */
    private function swooleRow(string $name, array $scenario, int $level): array
    {
        $this->swoole = new SwooleServer($this->sandbox->path());
        $port = $this->swoole->start($this->workers, 100000000, 'on');
        try {
            $url = 'http://127.0.0.1:' . $port . $scenario['path'];
            $this->waitUntilServing($url, $scenario['marker']);
            $load = new LoadGenerator($url, $scenario['marker']);
            $load->run(100, $level);
            $result = $load->run($this->requests, $level);
            // the memory is read over a second window: the first requests of a worker fill its caches
            $before = $this->swoole->workerMemory();
            $window = max($this->requests, $this->leakRequests);
            $load->run($window, $level);
            $growth = $this->growthPerThousand($before, $this->swoole->workerMemory(), $window);
        } finally {
            $this->swoole->stop();
        }
        return ['scenario' => $name, 'runtime' => 'swoole x' . $this->workers, 'concurrency' => $level, 'growth' => $growth] + $result;
    }

    /**
     * The port opens before the workers are ready to answer
     */
    private function waitUntilServing(string $url, string $marker): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                (new Probe($url, $marker))->request();
                return;
            } catch (\RuntimeException $exception) {
                if ($attempt >= 100) {
                    throw $exception;
                }
                usleep(100000);
            }
        }
    }

    /**
     * @param array<int,int> $before
     * @param array<int,int> $after
     * @return float KB of resident memory every worker gained per thousand requests (all workers together)
     */
    private function growthPerThousand(array $before, array $after, int $requests): float
    {
        $gained = 0;
        foreach ($after as $pid => $memory) {
            $gained += $memory - ($before[$pid] ?? $memory);
        }
        return $gained / max(1, $requests) * 1000;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function report(array $rows): string
    {
        $lines = [
            sprintf('## Swoole vs php -S (PHP %s, Swoole %s, %d requests per cell, opcache on, debug off)', PHP_VERSION, phpversion('swoole'), $this->requests),
            '',
            '| scenario | runtime | concurrency | req/s | p50 ms | p95 ms | worker memory growth KB per 1000 requests (second window) |',
            '|---|---|---|---|---|---|---|',
        ];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %d | %.0f | %.2f | %.2f | %s |',
                $row['scenario'], $row['runtime'], $row['concurrency'], $row['rps'], $row['p50'], $row['p95'],
                null === $row['growth'] ? '-' : sprintf('%.0f', $row['growth'])
            );
        }
        return implode("\n", $lines) . "\n";
    }

    private function cleanup(): void
    {
        $this->swoole?->stop();
        $this->builtIn?->stop();
        try {
            $database = (string)$this->sandbox?->database();
            if ('' !== $database) {
                $this->services?->dropDatabase($database);
            }
            $this->services?->removeCreatedKeys();
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'cleanup warning: ' . $exception->getMessage() . "\n");
        }
        $this->sandbox?->remove();
    }
}
