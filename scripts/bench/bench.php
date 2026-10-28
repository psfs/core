<?php
/**
 * PSFS request benchmark.
 *
 * Builds an isolated copy of the project under /tmp, serves it with `php -S` and measures real HTTP
 * requests for every combination of opcache, debug, number of modules, Redis and table size.
 * Nothing in the repository is modified: module generation, config, cache, logs and the MySQL
 * database all live in the sandbox and are removed at the end.
 *
 * Run it through scripts/bench/run.sh (it executes inside the php container). See scripts/bench/README.md.
 */
declare(strict_types=1);

use Psfs\Bench\Application;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Psfs\\Bench\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/lib/' . substr($class, strlen($prefix)) . '.php';
    }
});

exit((new Application(array_slice($argv, 1)))->run());
