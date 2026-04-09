<?php

namespace PSFS\tests\base\events;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\events\CloseSessionEvent;
use PSFS\base\types\interfaces\EventInterface;

class CloseSessionEventTest extends TestCase
{
    private array $serverBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER ?? [];
        $this->sessionBackup = $_SESSION ?? [];

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/setup',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_SESSION = [];

        Request::dropInstance();
        Security::dropInstance();
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        Request::dropInstance();
        Security::dropInstance();
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testInvokeStoresLastRequestStatsAndReturnsSuccessCode(): void
    {
        $event = new CloseSessionEvent();

        $status = $event();

        $this->assertSame(EventInterface::EVENT_SUCCESS, $status);
        $lastRequest = Security::getInstance()->getSessionKey('lastRequest');
        $this->assertIsArray($lastRequest);
        $this->assertStringContainsString('/admin/setup', (string)($lastRequest['url'] ?? ''));
        $this->assertIsNumeric($lastRequest['ts'] ?? null);
        $this->assertIsNumeric($lastRequest['eta'] ?? null);
        $this->assertIsNumeric($lastRequest['mem'] ?? null);
    }
}
