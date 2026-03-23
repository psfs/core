<?php

namespace PSFS\tests\base\command;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\CommandContext;
use Symfony\Component\Console\Application;

class CommandContextTest extends TestCase
{
    public function testProvidesConsoleAndMetadataAccess(): void
    {
        $console = new Application();
        $context = new CommandContext($console, ['domain' => 'ROOT', 'mode' => 'legacy']);

        $this->assertSame($console, $context->getConsole());
        $this->assertSame('ROOT', $context->getMetadata('domain'));
        $this->assertSame('fallback', $context->getMetadata('missing', 'fallback'));
        $this->assertSame(['domain' => 'ROOT', 'mode' => 'legacy'], $context->getMetadata());
    }

    public function testWithMetadataReturnsANewContextWithoutMutatingOriginal(): void
    {
        $console = new Application();
        $original = new CommandContext($console, ['domain' => 'ROOT']);
        $extended = $original->withMetadata('mode', 'adapter');

        $this->assertSame('ROOT', $original->getMetadata('domain'));
        $this->assertNull($original->getMetadata('mode'));
        $this->assertSame('adapter', $extended->getMetadata('mode'));
        $this->assertSame($console, $extended->getConsole());
    }
}

