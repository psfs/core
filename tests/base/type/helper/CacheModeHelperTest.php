<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\CacheModeHelper;

class CacheModeHelperTest extends TestCase
{
    public function testNormalizeSupportsKnownModesAndFallbacksToNone(): void
    {
        $this->assertSame('NONE', CacheModeHelper::normalize('none'));
        $this->assertSame('MEMORY', CacheModeHelper::normalize('MEMORY'));
        $this->assertSame('OPCACHE', CacheModeHelper::normalize('opcache'));
        $this->assertSame('REDIS', CacheModeHelper::normalize('redis'));
        $this->assertSame('NONE', CacheModeHelper::normalize('invalid-mode'));
    }

    public function testAllModesAreExposed(): void
    {
        $all = CacheModeHelper::all();
        $this->assertContains('NONE', $all);
        $this->assertContains('MEMORY', $all);
        $this->assertContains('OPCACHE', $all);
        $this->assertContains('REDIS', $all);
    }
}
