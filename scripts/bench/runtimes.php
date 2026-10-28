<?php
/**
 * Pieces of the runtime comparison (php -S, Swoole, nginx + php-fpm). scripts/bench/runtimes.sh drives them, because
 * the servers and the load generator run in different containers.
 *
 *   prepare --path=DIR              builds the sandbox, the database and the rows; prints {"db": ...}
 *   clean --path=DIR --db=NAME      removes them
 *   wait --url=URL --marker=TEXT    waits until the URL answers with the marker
 *   info --url=URL                  prints what the runtime reports about itself (bench-info.php)
 *   load --url=... --marker=... --requests=N --concurrency=C --scenario=S --runtime=R   prints one JSON row
 *   report --title=TEXT             reads the rows from stdin and prints the table
 */
declare(strict_types=1);

use Psfs\Bench\LoadGenerator;
use Psfs\Bench\Probe;
use Psfs\Bench\RuntimeFixture;
use Psfs\Bench\RuntimeReport;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Psfs\\Bench\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/lib/' . substr($class, strlen($prefix)) . '.php';
    }
});

$command = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    [$key, $value] = array_pad(explode('=', ltrim($argument, '-'), 2), 2, '');
    $options[$key] = $value;
}

switch ($command) {
    case 'prepare':
        echo json_encode(RuntimeFixture::prepare($options['path'])), PHP_EOL;
        break;
    case 'clean':
        RuntimeFixture::clean($options['path'], $options['db'] ?? '');
        break;
    case 'wait':
        waitUntilServing($options['url'], $options['marker']);
        break;
    case 'info':
        echo (string)@file_get_contents($options['url']), PHP_EOL;
        break;
    case 'load':
        echo json_encode(measure($options)), PHP_EOL;
        break;
    case 'report':
        echo RuntimeReport::fromLines(explode("\n", (string)stream_get_contents(STDIN)), $options['title'] ?? 'Runtime comparison');
        break;
    default:
        fwrite(STDERR, "Unknown command '$command'\n");
        exit(2);
}

function waitUntilServing(string $url, string $marker): void
{
    for ($attempt = 1; ; $attempt++) {
        try {
            (new Probe($url, $marker))->request();
            return;
        } catch (RuntimeException $exception) {
            if ($attempt >= 150) {
                throw $exception;
            }
            usleep(200000);
        }
    }
}

/**
 * @param array<string,string> $options
 * @return array<string,mixed>
 */
function measure(array $options): array
{
    $label = ['scenario' => $options['scenario'], 'runtime' => $options['runtime'], 'concurrency' => (int)$options['concurrency']];
    try {
        $load = new LoadGenerator($options['url'], $options['marker']);
        $load->run(max(100, (int)$options['concurrency'] * 10), (int)$options['concurrency']);
        return $label + $load->run((int)$options['requests'], (int)$options['concurrency']);
    } catch (Throwable $exception) {
        return $label + ['error' => $exception->getMessage()];
    }
}
