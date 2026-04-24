<?php

declare(strict_types=1);

$coverageFile = $argv[1] ?? '';
$threshold = isset($argv[2]) ? (float)$argv[2] : 90.0;

if ($coverageFile === '' || !file_exists($coverageFile)) {
    fwrite(STDERR, "[coverage] coverage file not found: {$coverageFile}\n");
    exit(1);
}

$xml = simplexml_load_file($coverageFile);
if (!$xml instanceof SimpleXMLElement) {
    fwrite(STDERR, "[coverage] invalid clover xml\n");
    exit(1);
}

$metrics = $xml->project->metrics ?? null;
if (!$metrics) {
    fwrite(STDERR, "[coverage] project metrics missing\n");
    exit(1);
}

$statements = (int)$metrics['statements'];
$covered = (int)$metrics['coveredstatements'];
$coverage = $statements > 0 ? ($covered * 100.0 / $statements) : 0.0;

printf(
    "[coverage] statements=%d covered=%d coverage=%.2f%% threshold=%.2f%%\n",
    $statements,
    $covered,
    $coverage,
    $threshold
);

if ($coverage < $threshold) {
    fwrite(STDERR, sprintf("[coverage] FAIL: %.2f%% < %.2f%%\n", $coverage, $threshold));
    exit(1);
}

echo "[coverage] OK\n";
exit(0);
