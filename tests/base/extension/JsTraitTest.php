<?php

namespace PSFS\tests\base\extension;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\extension\traits\JsTrait;

class JsTraitTest extends TestCase
{
    private array $configBackup = [];
    private string $dir;
    private object $host;

    protected function setUp(): void
    {
        $this->configBackup = (new \ReflectionProperty(Config::class, 'config'))->getValue(Config::getInstance());
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), array_merge($this->configBackup, ['debug' => false]));
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-js-' . uniqid('', true);
        mkdir($this->dir . '/src', 0777, true);
        $this->host = new class {
            use JsTrait;

            public function compile(array $files, string $basePath, string $hash): array
            {
                $compiled = [];
                $this->compileJs($files, $basePath, $hash, $compiled);
                return $compiled;
            }
        };
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), $this->configBackup);
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function bundle(): string
    {
        return $this->dir . '/js/abc.js';
    }

    private function source(string $name, string $code, int $mtime): string
    {
        $file = $this->dir . '/src/' . $name;
        file_put_contents($file, $code);
        touch($file, $mtime);
        return $file;
    }

    public function testProductionBundleIsBuiltFromTheSources(): void
    {
        $file = $this->source('app.js', 'var first = 1;', time() - 100);

        $this->host->compile([$file], $this->dir . '/', 'abc');

        self::assertFileExists($this->bundle());
        self::assertStringContainsString('first', (string)file_get_contents($this->bundle()));
        self::assertFileExists($this->dir . '/js/abc.manifest');
    }

    public function testChangedSourcesRebuildTheBundleInsteadOfEmptyingIt(): void
    {
        $file = $this->source('app.js', 'var first = 1;', time() - 100);
        $this->host->compile([$file], $this->dir . '/', 'abc');
        self::assertStringContainsString('first', (string)file_get_contents($this->bundle()));

        // A newer source makes the bundle stale: it used to be overwritten with an empty file
        $this->source('app.js', 'var second = 2;', time() + 100);
        $this->host->compile([$file], $this->dir . '/', 'abc');

        $content = (string)file_get_contents($this->bundle());
        self::assertNotSame('', $content, 'The rebuilt bundle must not be empty');
        self::assertStringContainsString('second', $content);
        self::assertStringNotContainsString('first', $content);
    }

    public function testAnUpToDateBundleIsNotRebuilt(): void
    {
        $file = $this->source('app.js', 'var first = 1;', time() - 100);
        $this->host->compile([$file], $this->dir . '/', 'abc');
        $built = filemtime($this->bundle());
        file_put_contents($this->bundle(), '/* untouched */');
        touch($this->bundle(), $built);

        $this->host->compile([$file], $this->dir . '/', 'abc');

        self::assertSame('/* untouched */', file_get_contents($this->bundle()));
    }

    public function testSeveralSourcesAreBundledTogetherWhenRebuilding(): void
    {
        $a = $this->source('a.js', 'var alpha = 1;', time() - 100);
        $b = $this->source('b.js', 'var beta = 2;', time() - 100);
        $this->host->compile([$a, $b], $this->dir . '/', 'abc');

        $this->source('b.js', 'var gamma = 3;', time() + 100);
        $this->host->compile([$a, $b], $this->dir . '/', 'abc');

        $content = (string)file_get_contents($this->bundle());
        self::assertStringContainsString('alpha', $content);
        self::assertStringContainsString('gamma', $content);
        self::assertStringNotContainsString('beta', $content);
    }
}
