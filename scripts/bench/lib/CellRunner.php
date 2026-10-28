<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Measures one cell of the matrix: a fresh server with the cell's settings, a cold request, a warm-up and the
 * measured requests
 */
final class CellRunner
{
    public function __construct(
        private readonly Options $options,
        private readonly Sandbox $sandbox,
        private readonly Server $server,
        private readonly Services $services
    ) {
    }

    /**
     * Measures the cell --repeat times and keeps the median of every metric
     *
     * @param array<string,mixed> $cell
     * @return array<string,mixed>
     */
    public function measure(array $cell): array
    {
        $runs = [];
        for ($i = 0, $repeat = max(1, $this->options->int('repeat')); $i < $repeat; $i++) {
            $runs[] = $this->measureOnce($cell);
        }
        return 1 === count($runs) ? $runs[0] : self::merge($runs);
    }

    /**
     * @param array<string,mixed> $cell
     * @return array<string,mixed>
     */
    private function measureOnce(array $cell): array
    {
        $scenario = Scenarios::get($cell['scenario']);
        $this->sandbox->writeConfig(['debug' => $cell['debug'], 'psfs.redis' => 'on' === $cell['redis']]);
        $this->sandbox->resetCaches();
        $statsFile = $this->sandbox->path() . '/stats.tsv';
        file_put_contents($statsFile, '');
        $port = $this->server->start($cell['opcache'], $cell['redis'], $statsFile);
        try {
            $sample = $this->sample('http://127.0.0.1:' . $port . $scenario['path'], $scenario['marker'], $statsFile);
        } finally {
            $this->server->stop();
        }
        return $this->summarise($cell, $sample, Stats::fromServerLog($statsFile));
    }

    /**
     * @return array{cold:float,times:float[],before:array<string,int>,after:array<string,int>}
     */
    private function sample(string $url, string $marker, string $statsFile): array
    {
        $probe = new Probe($url, $marker);
        $cold = $probe->request();
        $probe->sample($this->options->int('warmup'));
        // The server statistics and the counters must only cover the measured requests
        file_put_contents($statsFile, '');
        $before = $this->services->counters();
        $times = $probe->sample($this->options->int('iterations'));
        return ['cold' => $cold, 'times' => $times, 'before' => $before, 'after' => $this->services->counters()];
    }

    /**
     * @param array<string,mixed> $cell
     * @param array{cold:float,times:float[],before:array<string,int>,after:array<string,int>} $sample
     * @param array{php_p50:float,peak_mb:float,files:int,opcache:bool} $server
     * @return array<string,mixed>
     */
    private function summarise(array $cell, array $sample, array $server): array
    {
        $times = $sample['times'];
        $count = count($times);
        $perRequest = static fn(string $key, int $own = 0): float => max(0.0, ($sample['after'][$key] - $sample['before'][$key] - $own) / $count);
        $result = [
            'cold' => $sample['cold'],
            'p50' => Stats::percentile($times, 50), 'p95' => Stats::percentile($times, 95),
            'mean' => array_sum($times) / $count, 'min' => $times[0],
            'php_p50' => $server['php_p50'], 'peak_mb' => $server['peak_mb'], 'files' => $server['files'],
            'opcache_active' => $server['opcache'],
            // The statements that read the counters count themselves: one command or query per measurement
            'redis_conn' => $perRequest('redis_conn'), 'redis_cmd' => $perRequest('redis_cmd', 1),
            'db_conn' => $perRequest('db_conn'), 'db_q' => $perRequest('db_q', 1),
        ];
        if (('off' !== $cell['opcache']) !== $server['opcache']) {
            $result['warning'] = 'opcache state differs from the requested one';
        }
        return $result;
    }

    /**
     * @param array<int,array<string,mixed>> $runs
     * @return array<string,mixed>
     */
    private static function merge(array $runs): array
    {
        $merged = [];
        foreach ($runs[0] as $key => $value) {
            $merged[$key] = is_float($value) || is_int($value) ? Stats::median(array_column($runs, $key)) : $value;
        }
        $p50 = array_column($runs, 'p50');
        $merged['p50_min'] = min($p50);
        $merged['p50_max'] = max($p50);
        $warnings = array_column($runs, 'warning');
        if ([] !== $warnings) {
            $merged['warning'] = $warnings[0];
        }
        return $merged;
    }
}
