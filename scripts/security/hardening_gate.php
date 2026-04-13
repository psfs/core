<?php

declare(strict_types=1);

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function loadJson(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        fail("Cannot read JSON file: {$path}");
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fail("Invalid JSON structure: {$path}");
    }
    return $decoded;
}

function shouldApplyRule(array $rule): bool
{
    if (!isset($rule['when']) || !is_array($rule['when'])) {
        return true;
    }

    $when = $rule['when'];
    $env = (string)($when['env'] ?? '');
    if ($env === '') {
        return true;
    }

    $expected = (string)($when['equals'] ?? '1');
    $current = (string)getenv($env);

    return $current === $expected;
}

function evaluateRule(array $rule, array $config): ?array
{
    $id = (string)($rule['id'] ?? 'UNKNOWN');
    $key = (string)($rule['key'] ?? '');
    $type = (string)($rule['type'] ?? '');
    $severity = strtolower((string)($rule['severity'] ?? 'low'));

    if ($key === '' || $type === '') {
        return [
            'id' => $id,
            'severity' => 'high',
            'message' => 'Invalid policy rule definition',
        ];
    }

    $value = array_key_exists($key, $config) ? $config[$key] : ($rule['default'] ?? null);

    if ($type === 'exact') {
        $expected = $rule['expected'] ?? null;
        if ($value !== $expected) {
            return [
                'id' => $id,
                'severity' => $severity,
                'message' => "Expected {$key}=" . json_encode($expected) . ", got " . json_encode($value),
            ];
        }
        return null;
    }

    if ($type === 'non_empty_string') {
        if (!is_string($value) || trim($value) === '') {
            return [
                'id' => $id,
                'severity' => $severity,
                'message' => "Expected non-empty string for {$key}",
            ];
        }
        return null;
    }

    if ($type === 'forbid_exact') {
        $forbidden = $rule['forbidden'] ?? null;
        if ($value === $forbidden) {
            return [
                'id' => $id,
                'severity' => $severity,
                'message' => "Forbidden value {$key}=" . json_encode($forbidden),
            ];
        }
        return null;
    }

    return [
        'id' => $id,
        'severity' => 'high',
        'message' => "Unsupported policy rule type: {$type}",
    ];
}

$policyPath = getenv('PSFS_SECURITY_HARDENING_POLICY') ?: 'security/contracts/hardening-policy.json';
$configPath = getenv('PSFS_SECURITY_CONFIG_FILE') ?: 'config/config.json';
$reportPath = getenv('PSFS_SECURITY_HARDENING_REPORT') ?: 'security/reports/hardening-gate.json';

$policy = loadJson($policyPath);
$config = loadJson($configPath);
$rules = $policy['rules'] ?? [];
if (!is_array($rules)) {
    fail('Invalid hardening policy rules');
}

$violations = [];
foreach ($rules as $rule) {
    if (!is_array($rule) || !shouldApplyRule($rule)) {
        continue;
    }

    $violation = evaluateRule($rule, $config);
    if (is_array($violation)) {
        $violations[] = $violation;
    }
}

$blocking = array_values(array_filter($violations, static fn(array $violation): bool => in_array(
    strtolower((string)($violation['severity'] ?? 'low')),
    ['high', 'critical'],
    true
)));

$status = empty($blocking) ? 'pass' : 'block';

$report = [
    'version' => '1.0',
    'generated_at' => gmdate('c'),
    'status' => $status,
    'summary' => [
        'rules_evaluated' => count($rules),
        'violations' => count($violations),
        'blocking' => count($blocking),
    ],
    'violations' => $violations,
];

if (!is_dir(dirname($reportPath))) {
    mkdir(dirname($reportPath), 0775, true);
}
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
out("[HARDENING_GATE] status={$status} report={$reportPath}");

exit($status === 'pass' ? 0 : 1);
