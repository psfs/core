<?php

namespace PSFS\base\benchmark;

use RuntimeException;

final class RuntimeMatrixReportWriter
{
    public function __construct(private string $outputDir)
    {
    }

    public function ensureOutputDirectory(): void
    {
        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException('Unable to create output directory: ' . $this->outputDir);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $scenarioResults
     * @return array<string, mixed>
     */
    public function buildSummary(array $scenarioResults): array
    {
        $rows = [];
        foreach ($scenarioResults as $scenarioResult) {
            array_push($rows, ...$this->scenarioRows($scenarioResult));
        }

        return [
            'scenario_count' => count($scenarioResults),
            'row_count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function writeReports(array $report): void
    {
        $timestamp = gmdate('Ymd_His');
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to serialize benchmark report');
        }
        $this->writeReportFile('runtime-matrix-' . $timestamp . '.json', $json . PHP_EOL);
        $this->writeReportFile('runtime-matrix-' . $timestamp . '.md', $this->buildMarkdownSummary($report));
        $this->writeReportFile('latest.json', $json . PHP_EOL);
        $this->writeReportFile('latest.md', $this->buildMarkdownSummary($report));
    }

    /**
     * @param array<string, mixed> $report
     */
    public function buildMarkdownSummary(array $report): string
    {
        $lines = [
            '# Runtime Matrix Benchmark',
            '',
            '| runtime | debug | opcache | redis | profile | rps | p95_ms | p99_ms | error_rate |',
            '|---|---:|---:|---:|---|---:|---:|---:|---:|',
        ];
        foreach ($report['summary']['rows'] as $row) {
            $lines[] = $this->markdownRow($row);
        }
        $lines[] = '';
        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $scenarioResult
     * @return array<int, array<string, mixed>>
     */
    private function scenarioRows(array $scenarioResult): array
    {
        $rows = [];
        $scenario = $scenarioResult['scenario'];
        foreach ($scenarioResult['profiles'] as $profile) {
            $rows[] = [
                'runtime' => $scenario['runtime'],
                'debug' => $scenario['debug'] ? 1 : 0,
                'opcache' => $scenario['opcache'] ? 1 : 0,
                'redis' => $scenario['redis'] ? 1 : 0,
                'profile' => $profile['profile'],
                'rps' => $profile['rps'],
                'p95_ms' => $profile['p95_ms'],
                'p99_ms' => $profile['p99_ms'],
                'error_rate' => $profile['error_rate'],
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function markdownRow(array $row): string
    {
        return sprintf(
            '| %s | %d | %d | %d | %s | %.2f | %.3f | %.3f | %.6f |',
            $row['runtime'],
            $row['debug'],
            $row['opcache'],
            $row['redis'],
            $row['profile'],
            $row['rps'],
            $row['p95_ms'],
            $row['p99_ms'],
            $row['error_rate']
        );
    }

    private function writeReportFile(string $filename, string $content): void
    {
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . $filename, $content);
    }
}
