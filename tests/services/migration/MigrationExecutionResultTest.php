<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\MigrationExecutionResult;

class MigrationExecutionResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = MigrationExecutionResult::success('phinx', 'done', 'phinx migrate');

        $this->assertSame('phinx', $result->getEngine());
        $this->assertTrue($result->isSuccess());
        $this->assertSame(0, $result->getExitCode());
        $this->assertSame('done', $result->getOutput());
        $this->assertSame('phinx migrate', $result->getCommand());
    }

    public function testFailureFactory(): void
    {
        $result = MigrationExecutionResult::failure('propel', 'error', 42, 'propel migrate');

        $this->assertSame('propel', $result->getEngine());
        $this->assertFalse($result->isSuccess());
        $this->assertSame(42, $result->getExitCode());
        $this->assertSame('error', $result->getOutput());
        $this->assertSame('propel migrate', $result->getCommand());
    }
}
