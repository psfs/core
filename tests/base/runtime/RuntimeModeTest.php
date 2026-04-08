<?php

namespace PSFS\tests\base\runtime;

use PHPUnit\Framework\TestCase;
use PSFS\base\runtime\RuntimeMode;

class RuntimeModeTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(RuntimeMode::ENV_KEY);
        unset($_SERVER[RuntimeMode::ENV_KEY]);
    }

    public function testEnableSwooleSetsLongRunningMode(): void
    {
        RuntimeMode::enableSwoole();
        $this->assertTrue(RuntimeMode::isLongRunningServer());
        $this->assertSame('swoole', RuntimeMode::getCurrentMode());
    }
}
