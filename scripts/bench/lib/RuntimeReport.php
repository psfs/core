<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The table of the runtime comparison, built from one JSON object per measured cell
 */
final class RuntimeReport
{
    /**
     * @param string[] $lines JSON objects: scenario, runtime, concurrency, rps, p50, p95 (or error)
     */
    public static function fromLines(array $lines, string $title): string
    {
        $rows = array_map(static fn(string $line): array => json_decode($line, true) ?: [], array_values(array_filter(array_map('trim', $lines), 'strlen')));
        $table = [
            $title,
            '',
            '| scenario | runtime | concurrency | req/s | p50 ms | p95 ms |',
            '|---|---|---|---|---|---|',
        ];
        foreach ($rows as $row) {
            $table[] = isset($row['error'])
                ? sprintf('| %s | %s | %d | failed: %s | | |', $row['scenario'], $row['runtime'], $row['concurrency'], $row['error'])
                : sprintf('| %s | %s | %d | %.0f | %.2f | %.2f |', $row['scenario'], $row['runtime'], $row['concurrency'], $row['rps'], $row['p50'], $row['p95']);
        }
        return implode("\n", $table) . "\n";
    }
}
