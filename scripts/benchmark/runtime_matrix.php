<?php

declare(strict_types=1);

use PSFS\base\benchmark\RuntimeMatrixRunner;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2);
$quickMode = in_array('--quick', $argv, true);

$runner = new RuntimeMatrixRunner(
    $projectRoot,
    null,
    null,
    null,
    $quickMode ? [
        ['name' => 'L1', 'concurrency' => 1, 'requests' => 200],
        ['name' => 'L2', 'concurrency' => 10, 'requests' => 500],
        ['name' => 'L3', 'concurrency' => 25, 'requests' => 800],
    ] : null,
    $quickMode ? 100 : 500,
    $quickMode ? 20 : 40,
    $quickMode ? 6 : 10
);

try {
    $report = $runner->run();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[runtime-matrix] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
