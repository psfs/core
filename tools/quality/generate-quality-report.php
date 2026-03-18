<?php

declare(strict_types=1);

/**
 * Builds quality-report.json and quality-report.md from test output, coverage and static hotspots.
 *
 * Usage:
 * php tools/quality/generate-quality-report.php \
 *   --phpunit-output=cache/quality/phpunit-output.txt \
 *   --coverage=cache/coverage/coverage.xml \
 *   --scrutinizer=cache/quality/scrutinizer.json \
 *   --output-json=quality-report.json \
 *   --output-md=quality-report.md
 */

$options = getopt('', [
    'phpunit-output::',
    'coverage::',
    'scrutinizer::',
    'output-json::',
    'output-md::',
    'base-dir::',
]);

$baseDir = realpath($options['base-dir'] ?? getcwd()) ?: getcwd();
$phpunitOutputPath = $options['phpunit-output'] ?? $baseDir . '/cache/quality/phpunit-output.txt';
$coveragePath = $options['coverage'] ?? $baseDir . '/cache/coverage/coverage.xml';
$scrutinizerPath = $options['scrutinizer'] ?? $baseDir . '/cache/quality/scrutinizer.json';
$outputJsonPath = $options['output-json'] ?? $baseDir . '/quality-report.json';
$outputMdPath = $options['output-md'] ?? $baseDir . '/quality-report.md';

$report = [
    'generated_at' => gmdate(DATE_ATOM),
    'summary' => [
        'tests' => null,
        'assertions' => null,
        'time_seconds' => null,
        'memory' => null,
        'coverage_line_rate' => null,
        'coverage_percent' => null,
    ],
    'scrutinizer' => [
        'delta' => null,
        'note' => 'No Scrutinizer data available',
    ],
    'hotspots' => [],
    'notes' => [],
];

$phpunitOutput = is_file($phpunitOutputPath) ? (string)file_get_contents($phpunitOutputPath) : '';
if ($phpunitOutput !== '') {
    if (preg_match('/Tests:\s+(\d+),\s+Assertions:\s+(\d+)/', $phpunitOutput, $matches) === 1) {
        $report['summary']['tests'] = (int)$matches[1];
        $report['summary']['assertions'] = (int)$matches[2];
    } elseif (preg_match('/OK\s+\((\d+)\s+tests?,\s+(\d+)\s+assertions?\)/i', $phpunitOutput, $matches) === 1) {
        $report['summary']['tests'] = (int)$matches[1];
        $report['summary']['assertions'] = (int)$matches[2];
    }
    if (preg_match('/Time:\s+([0-9:\.]+),\s+Memory:\s+([^\n\r]+)/', $phpunitOutput, $matches) === 1) {
        $report['summary']['time_seconds'] = parseDuration((string)$matches[1]);
        $report['summary']['memory'] = trim((string)$matches[2]);
    }
    if (str_contains($phpunitOutput, 'Risky:')) {
        $report['notes'][] = 'PHPUnit reported risky tests. Review phpunit-output artifact.';
    }
}

if (is_file($coveragePath)) {
    $xml = @simplexml_load_file($coveragePath);
    if ($xml !== false) {
        $lineRate = null;
        if (isset($xml['line-rate'])) {
            $lineRate = (float)$xml['line-rate'];
        } else {
            $metrics = $xml->xpath('//project/metrics');
            if (is_array($metrics) && !empty($metrics)) {
                $projectMetrics = $metrics[0];
                $statements = (int)($projectMetrics['statements'] ?? 0);
                $coveredStatements = (int)($projectMetrics['coveredstatements'] ?? 0);
                if ($statements > 0) {
                    $lineRate = $coveredStatements / $statements;
                }
            }
        }
        if (is_float($lineRate)) {
            $report['summary']['coverage_line_rate'] = $lineRate;
            $report['summary']['coverage_percent'] = round($lineRate * 100, 2);
        }
    }
} else {
    $report['notes'][] = 'Coverage report not found';
}

if (is_file($scrutinizerPath)) {
    $scrutinizer = json_decode((string)file_get_contents($scrutinizerPath), true);
    if (is_array($scrutinizer)) {
        $report['scrutinizer']['delta'] = array_key_exists('delta', $scrutinizer)
            ? $scrutinizer['delta']
            : null;
        $report['scrutinizer']['note'] = (string)($scrutinizer['note'] ?? 'Scrutinizer data loaded');
    }
}

$report['hotspots'] = buildHotspots($baseDir, 10);
if (empty($report['hotspots'])) {
    $report['notes'][] = 'No hotspots detected in src/base or src/services';
}

@file_put_contents($outputJsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
@file_put_contents($outputMdPath, renderMarkdown($report));

function parseDuration(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (!str_contains($raw, ':')) {
        return (float)$raw;
    }
    $parts = array_map('trim', explode(':', $raw));
    if (count($parts) === 2) {
        return ((float)$parts[0] * 60) + (float)$parts[1];
    }
    if (count($parts) === 3) {
        return ((float)$parts[0] * 3600) + ((float)$parts[1] * 60) + (float)$parts[2];
    }
    return null;
}

/**
 * @return array<int, array<string, int|string|float>>
 */
function buildHotspots(string $baseDir, int $limit): array
{
    $targets = [$baseDir . '/src/base', $baseDir . '/src/services'];
    $hotspots = [];

    foreach ($targets as $target) {
        if (!is_dir($target)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $code = (string)@file_get_contents($path);
            if ($code === '') {
                continue;
            }
            $loc = substr_count($code, "\n") + 1;
            $decisionPoints = preg_match_all('/\b(if|elseif|for|foreach|while|case|catch)\b|&&|\|\||\?/', $code) ?: 0;
            $functions = preg_match_all('/\bfunction\s+[a-zA-Z0-9_]+\s*\(/', $code) ?: 0;
            $score = ($decisionPoints * 2) + (int)ceil($loc / 80) + $functions;
            $hotspots[] = [
                'file' => ltrim(str_replace($baseDir, '', $path), '/'),
                'score' => $score,
                'loc' => $loc,
                'decision_points' => $decisionPoints,
                'functions' => $functions,
            ];
        }
    }

    usort($hotspots, static function (array $a, array $b): int {
        return ($b['score'] <=> $a['score']);
    });

    return array_slice($hotspots, 0, $limit);
}

function renderMarkdown(array $report): string
{
    $summary = $report['summary'];
    $lines = [
        '# Quality Report',
        '',
        '- Generated at: `' . (string)$report['generated_at'] . '`',
        '- PHPUnit tests: `' . toStringOrNull($summary['tests']) . '`',
        '- Assertions: `' . toStringOrNull($summary['assertions']) . '`',
        '- Runtime (s): `' . toStringOrNull($summary['time_seconds']) . '`',
        '- Memory: `' . toStringOrNull($summary['memory']) . '`',
        '- Coverage (%): `' . toStringOrNull($summary['coverage_percent']) . '`',
        '- Scrutinizer delta: `' . toStringOrNull($report['scrutinizer']['delta']) . '`',
        '- Scrutinizer note: ' . (string)$report['scrutinizer']['note'],
        '',
        '## Complexity Hotspots',
        '',
        '| File | Score | LOC | Decisions | Functions |',
        '| --- | ---: | ---: | ---: | ---: |',
    ];

    foreach ($report['hotspots'] as $hotspot) {
        $lines[] = sprintf(
            '| `%s` | %d | %d | %d | %d |',
            (string)$hotspot['file'],
            (int)$hotspot['score'],
            (int)$hotspot['loc'],
            (int)$hotspot['decision_points'],
            (int)$hotspot['functions']
        );
    }

    if (!empty($report['notes'])) {
        $lines[] = '';
        $lines[] = '## Notes';
        $lines[] = '';
        foreach ($report['notes'] as $note) {
            $lines[] = '- ' . (string)$note;
        }
    }

    $lines[] = '';
    return implode(PHP_EOL, $lines);
}

function toStringOrNull(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'null';
    }
    if (is_float($value)) {
        return (string)round($value, 3);
    }
    return (string)$value;
}
