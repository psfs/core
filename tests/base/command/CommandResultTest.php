<?php

namespace PSFS\tests\base\command;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\CommandResult;

class CommandResultTest extends TestCase
{
    public function testSuccessFactoryCreatesSuccessfulResult(): void
    {
        $result = CommandResult::success(3, 'Loaded');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(3, $result->getRegisteredCommands());
        $this->assertSame('Loaded', $result->getMessage());
    }

    public function testFailureFactoryCreatesFailedResult(): void
    {
        $result = CommandResult::failure('Broken file');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(0, $result->getRegisteredCommands());
        $this->assertSame('Broken file', $result->getMessage());
    }
}

