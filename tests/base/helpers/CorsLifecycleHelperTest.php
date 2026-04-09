<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\exception\RequestTerminationException;
use PSFS\base\types\helpers\CorsLifecycleHelper;
use PSFS\base\types\helpers\ResponseHelper;

class CorsLifecycleHelperTest extends TestCase
{
    private array $headersBackup = [];
    private mixed $runtimeBackup = null;

    protected function setUp(): void
    {
        $this->headersBackup = ResponseHelper::$headers_sent;
        $this->runtimeBackup = $_SERVER['PSFS_RUNTIME'] ?? null;
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
    }

    protected function tearDown(): void
    {
        ResponseHelper::$headers_sent = $this->headersBackup;
        if ($this->runtimeBackup === null) {
            unset($_SERVER['PSFS_RUNTIME']);
        } else {
            $_SERVER['PSFS_RUNTIME'] = $this->runtimeBackup;
        }
    }

    public function testApplySetsCorsHeadersForStandardRequests(): void
    {
        CorsLifecycleHelper::apply('https://example.com', false, ['Content-Type', 'Authorization']);

        $this->assertSame('true', ResponseHelper::$headers_sent['access-control-allow-credentials'] ?? null);
        $this->assertSame('https://example.com', ResponseHelper::$headers_sent['access-control-allow-origin'] ?? null);
        $this->assertSame('Origin', ResponseHelper::$headers_sent['vary'] ?? null);
        $this->assertStringContainsString(
            'Content-Type',
            (string)(ResponseHelper::$headers_sent['access-control-allow-headers'] ?? '')
        );
    }

    public function testApplyThrowsTerminationOnLongRunningPreflight(): void
    {
        $_SERVER['PSFS_RUNTIME'] = 'swoole';
        $this->expectException(RequestTerminationException::class);
        CorsLifecycleHelper::apply('https://example.com', true, ['Content-Type']);
    }
}
