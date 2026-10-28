<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Prints the results as a Markdown table and, on request, as JSON
 */
final class Reporter
{
    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $results
     */
    public function print(array $results): void
    {
        $this->printIntroduction();
        $columns = $this->columns();
        echo '| ' . implode(' | ', $columns) . " |\n|" . str_repeat('---|', count($columns)) . "\n";
        foreach ($results as $result) {
            echo isset($result['error']) ? $this->failedRow($result) : '| ' . implode(' | ', $this->row($result)) . " |\n";
        }
        if ([] !== array_column($results, 'warning')) {
            echo "\n`!` after the opcache value: the server reported a different opcache state than requested; do not trust that row.\n";
        }
        $this->writeJson($results);
    }

    private function printIntroduction(): void
    {
        $repeat = $this->options->int('repeat');
        $suffix = $repeat > 1 ? sprintf(', median of %d runs', $repeat) : '';
        echo "\n## PSFS benchmark results\n\n";
        printf(
            "PHP %s, %s requests per cell after %s warm-up%s. Times are wall clock through HTTP (php -S, one process, no keep-alive).\n",
            PHP_VERSION,
            $this->options->text('iterations'),
            $this->options->text('warmup'),
            $suffix
        );
        echo "`php p50` is the time inside PHP (request start to shutdown). `conn`/`cmd`/`q` are per request: Redis connections and commands, MySQL connections and queries.\n";
        echo "`est@N` = p50 + (db q + redis cmd + 2 x (db conn + redis conn)) x N ms: a rough model of the same request with Redis/MySQL N ms away.\n\n";
    }

    /**
     * @return string[]
     */
    private function columns(): array
    {
        $range = $this->options->int('repeat') > 1 ? ['p50 range'] : [];
        $columns = array_merge(
            ['scenario', 'mods', 'rows', 'opcache', 'debug', 'redis', 'cold ms', 'p50'],
            $range,
            ['p95', 'php p50', 'peak MB', 'files', 'redis conn', 'redis cmd', 'db conn', 'db q']
        );
        foreach ($this->roundTripTimes() as $rtt) {
            $columns[] = 'est@' . rtrim(rtrim(number_format($rtt, 1), '0'), '.');
        }
        return $columns;
    }

    /**
     * @return float[]
     */
    private function roundTripTimes(): array
    {
        return array_map('floatval', $this->options->list('rtt'));
    }

    /**
     * @param array<string,mixed> $result
     * @return string[]
     */
    private function row(array $result): array
    {
        $row = array_merge(
            $this->identity($result),
            [number_format($result['cold'], 1), number_format($result['p50'], 2)],
            $this->options->int('repeat') > 1 ? [$this->range($result)] : [],
            [
                number_format($result['p95'], 2), number_format($result['php_p50'], 2), number_format($result['peak_mb'], 1), (string)$result['files'],
                number_format($result['redis_conn'], 2), number_format($result['redis_cmd'], 1),
                number_format($result['db_conn'], 2), number_format($result['db_q'], 1),
            ]
        );
        $roundTrips = $result['db_q'] + $result['redis_cmd'] + 2 * ($result['db_conn'] + $result['redis_conn']);
        foreach ($this->roundTripTimes() as $rtt) {
            $row[] = number_format($result['p50'] + $roundTrips * $rtt, 1);
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $result
     * @return string[]
     */
    private function identity(array $result): array
    {
        return [
            $result['scenario'], (string)$result['modules'], Scenarios::isApi($result['scenario']) ? (string)$result['rows'] : '-',
            $result['opcache'] . (isset($result['warning']) ? '!' : ''), $result['debug'] ? 'true' : 'false', $result['redis'],
        ];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function range(array $result): string
    {
        return number_format($result['p50_min'] ?? $result['p50'], 2) . '-' . number_format($result['p50_max'] ?? $result['p50'], 2);
    }

    /**
     * @param array<string,mixed> $result
     */
    private function failedRow(array $result): string
    {
        return sprintf(
            "| %s | %d | %d | %s | %s | %s | FAILED: %s |\n",
            $result['scenario'], $result['modules'], $result['rows'], $result['opcache'], $result['debug'] ? 'true' : 'false', $result['redis'], $result['error']
        );
    }

    /**
     * @param array<int,array<string,mixed>> $results
     */
    private function writeJson(array $results): void
    {
        if (!$this->options->has('json')) {
            return;
        }
        $path = $this->options->text('json');
        file_put_contents($path, json_encode(['php' => PHP_VERSION, 'options' => $this->options->toArray(), 'results' => $results], JSON_PRETTY_PRINT));
        fprintf(STDERR, "raw results written to %s\n", $path);
    }
}
