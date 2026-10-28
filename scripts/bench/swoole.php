<?php
/**
 * Swoole benchmark: latency, throughput and worker memory growth of the PSFS Swoole runtime against php -S.
 * Run it through scripts/bench/swoole.sh (it executes inside the php container, where ext-swoole is available).
 *
 *   --workers=2        Swoole worker processes
 *   --requests=1000    requests per cell
 *   --concurrency=1,8  simultaneous connections
 *   --leak=0           size of the window that measures memory growth, when larger than --requests
 */
declare(strict_types=1);

use Psfs\Bench\SwooleBenchmark;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Psfs\\Bench\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/lib/' . substr($class, strlen($prefix)) . '.php';
    }
});

$values = ['workers' => '2', 'requests' => '1000', 'concurrency' => '1,8', 'leak' => '0'];
foreach (array_slice($argv, 1) as $argument) {
    [$key, $value] = array_pad(explode('=', ltrim($argument, '-'), 2), 2, '');
    if (!isset($values[$key])) {
        fwrite(STDERR, "Unknown option --$key\n");
        exit(2);
    }
    $values[$key] = $value;
}
echo (new SwooleBenchmark(
    (int)$values['workers'],
    (int)$values['requests'],
    array_map('intval', explode(',', $values['concurrency'])),
    (int)$values['leak']
))->run();
