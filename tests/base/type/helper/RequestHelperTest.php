<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\RequestHelper;

class RequestHelperTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testGetIpAddressPrioritizesForwardedForChain(): void
    {
        $_SERVER = [
            'HTTP_CLIENT_IP' => 'invalid-ip',
            'HTTP_X_FORWARDED_FOR' => 'unknown, 10.0.0.1, 203.0.113.20',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $this->assertSame('10.0.0.1', RequestHelper::getIpAddress());
    }

    public function testGetIpAddressFallsBackToRemoteAddr(): void
    {
        $_SERVER = [
            'HTTP_X_FORWARDED_FOR' => 'invalid-ip',
            'HTTP_X_FORWARDED' => 'invalid-ip',
            'HTTP_X_CLUSTER_CLIENT_IP' => '',
            'REMOTE_ADDR' => '198.51.100.7',
        ];

        $this->assertSame('198.51.100.7', RequestHelper::getIpAddress());
    }
}
