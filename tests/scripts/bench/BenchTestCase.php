<?php

namespace PSFS\tests\scripts\bench;

use PHPUnit\Framework\TestCase;

/**
 * The benchmark classes live in scripts/bench/lib and are not part of the Composer autoloader
 */
abstract class BenchTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Psfs\\Bench\\';
            if (str_starts_with($class, $prefix)) {
                require_once dirname(__DIR__, 3) . '/scripts/bench/lib/' . substr($class, strlen($prefix)) . '.php';
            }
        });
    }
}
