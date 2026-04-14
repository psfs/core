<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\exception\LoggerException;

class LoggerExceptionTest extends TestCase
{
    public function testGetErrorWrapsMessageInHtmlContainer(): void
    {
        $exception = new LoggerException('boom');
        $html = $exception->getError();

        $this->assertStringContainsString('boom', $html);
        $this->assertStringContainsString('<p style=', $html);
        $this->assertStringContainsString('</p>', $html);
    }
}
