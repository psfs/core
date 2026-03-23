<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\MigrationExecutionContext;

class MigrationExecutionContextTest extends TestCase
{
    public function testExposesExecutionContextValues(): void
    {
        $context = new MigrationExecutionContext('Client', '/tmp/config', '/tmp/migrations', true);

        $this->assertSame('Client', $context->getModule());
        $this->assertSame('client', $context->getModuleLower());
        $this->assertSame('/tmp/config', $context->getConfigDir());
        $this->assertSame('/tmp/migrations', $context->getMigrationDir());
        $this->assertTrue($context->isSimulate());
    }
}
