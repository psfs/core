<?php

use PHPUnit\Framework\TestCase;
use PSFS\Autoloader;

final class AutoloaderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('SOURCE_DIR')) {
            define('SOURCE_DIR', __DIR__ . '/../src');
        }

        require_once SOURCE_DIR . '/../src/autoload.php';
        Autoloader::register();
    }

    public function testValidClassIsAutoloaded(): void
    {
        $class = 'PSFS\\tests\\fixtures\\DummyClass';

        $this->assertFalse(class_exists($class, false));

        $loaded = class_exists($class);

        $this->assertTrue($loaded);
        $this->assertTrue(method_exists($class, 'hello'));

        $this->assertSame('Hello from DummyClass', $class::hello());
    }


    public function testIgnoresNonPSFSClass(): void
    {
        $this->assertFalse(class_exists('External\\Other\\Whatever', false));
        spl_autoload_call('External\\Other\\Whatever');
        $this->assertFalse(class_exists('External\\Other\\Whatever', false));
    }

    public function testHandlesMissingPSFSClassWithoutLogger(): void
    {
        // Logger no existe, no debe lanzar nada
        $this->assertFalse(class_exists('PSFS\\NonExistent\\GhostClass', false));
        $this->assertFalse(class_exists('PSFS\\NonExistent\\GhostClass'));
    }

    public function testHandlesMissingClassWithLogger(): void
    {
        $class = 'PSFS\\NonExistent\\LoggerGhost';
        $this->assertFalse(class_exists($class, false));
        $this->assertFalse(class_exists($class));
    }
}
