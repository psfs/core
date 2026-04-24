<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;

class BootstrapCoverageTest extends TestCase
{
    public function testBootstrapAndPolyfillsAreLoadable(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $polyfillBootstrap = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'polyfills' . DIRECTORY_SEPARATOR . 'bootstrap.php';
        $polyfillRedis = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'polyfills' . DIRECTORY_SEPARATOR . 'testing' . DIRECTORY_SEPARATOR . 'redis.php';

        $this->assertFileExists($bootstrap);
        $this->assertFileExists($polyfillBootstrap);
        $this->assertFileExists($polyfillRedis);

        include $polyfillRedis;
        include $polyfillBootstrap;
        include $bootstrap;

        $this->assertTrue(defined('BASE_DIR'));
        $this->assertTrue(defined('CACHE_DIR'));
        $this->assertTrue(class_exists(\Redis::class));
    }
}
