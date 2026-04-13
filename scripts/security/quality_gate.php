<?php

declare(strict_types=1);

/**
 * PSFS Security Quality Gate
 * - Blocks on open high/critical findings
 * - Executes must_pass tests from control-matrix.yaml
 * - Emits doc/security/quality-gate.json
 */

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parseMustPassTests(string $controlMatrixPath): array
{
    $lines = @file($controlMatrixPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("Cannot read control matrix: {$controlMatrixPath}");
    }

    $tests = [];
    $currentTest = null;

    foreach ($lines as $line) {
        if (preg_match('/\btest:\s*"([^"]+)"/', $line, $match) === 1) {
            $currentTest = trim($match[1]);
            continue;
        }

        if ($currentTest !== null && preg_match('/\bgate:\s*"must_pass"/', $line) === 1) {
            $tests[] = $currentTest;
            $currentTest = null;
            continue;
        }

        if (preg_match('/^\s*-\s+control_id:/', $line) === 1) {
            $currentTest = null;
        }
    }

    return array_values(array_unique($tests));
}

function loadFindings(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        fail("Cannot read findings file: {$path}");
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['findings']) || !is_array($decoded['findings'])) {
        fail("Invalid findings JSON structure: {$path}");
    }

    return $decoded['findings'];
}

function hasBlockingFindings(array $findings): array
{
    $blocking = [];
    foreach ($findings as $finding) {
        $severity = strtolower((string)($finding['severidad'] ?? ''));
        $status = strtolower((string)($finding['status'] ?? 'open'));
        if (in_array($severity, ['critical', 'high'], true) && !in_array($status, ['resolved', 'accepted'], true)) {
            $blocking[] = $finding;
        }
    }

    return $blocking;
}

function runTests(array $tests): array
{
    $results = [];

    foreach ($tests as $test) {
        $cmd = 'php vendor/bin/phpunit --colors=never --no-coverage --do-not-fail-on-warning --do-not-fail-on-risky --filter '
            . escapeshellarg('/^.*' . preg_quote($test, '/') . '$/')
            . ' 2>&1';
        out("[QUALITY_GATE] Running must_pass test: {$test}");

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        $results[] = [
            'test' => $test,
            'exit_code' => $exit,
            'ok' => $exit === 0,
            'output' => implode("\n", $output),
        ];
    }

    return $results;
}

$controlMatrixPath = getenv('PSFS_SECURITY_CONTROL_MATRIX') ?: 'security/contracts/control-matrix.yaml';
$findingsPath = getenv('PSFS_SECURITY_FINDINGS') ?: 'security/contracts/findings.json';
$outputPath = getenv('PSFS_SECURITY_QUALITY_GATE_REPORT') ?: 'security/reports/quality-gate.json';

$mustPassTests = parseMustPassTests($controlMatrixPath);
$findings = loadFindings($findingsPath);
$blockingFindings = hasBlockingFindings($findings);
$testResults = runTests($mustPassTests);

$failedTests = array_values(array_filter($testResults, static fn(array $result): bool => $result['ok'] === false));
$status = (empty($blockingFindings) && empty($failedTests)) ? 'pass' : 'block';

$report = [
    'version' => '1.0',
    'generated_at' => gmdate('c'),
    'status' => $status,
    'summary' => [
        'must_pass_total' => count($mustPassTests),
        'must_pass_failed' => count($failedTests),
        'blocking_findings' => count($blockingFindings),
    ],
    'blocking_findings' => $blockingFindings,
    'tests' => $testResults,
];

if (!is_dir(dirname($outputPath))) {
    mkdir(dirname($outputPath), 0775, true);
}
file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
out("[QUALITY_GATE] status={$status} report={$outputPath}");

exit($status === 'pass' ? 0 : 1);
