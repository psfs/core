<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\traits\Security\SessionTrait;

class SessionTraitRuntimeTest extends TestCase
{
    private string $runtimeBackup = '';
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->runtimeBackup = RuntimeMode::getCurrentMode();
        $this->sessionBackup = $_SESSION ?? [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        @session_id('');
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if ($this->runtimeBackup !== '') {
            putenv(RuntimeMode::ENV_KEY . '=' . $this->runtimeBackup);
            $_SERVER[RuntimeMode::ENV_KEY] = $this->runtimeBackup;
        } else {
            putenv(RuntimeMode::ENV_KEY);
            unset($_SERVER[RuntimeMode::ENV_KEY]);
        }
        $_SESSION = $this->sessionBackup;
        @session_id('');
    }

    public function testInitSessionWorksInLongRunningMode(): void
    {
        RuntimeMode::enableSwoole();
        $harness = new SessionTraitHarness();

        $harness->bootSession();

        $this->assertNotNull($harness->sessionPayload());
        $this->assertSame($_SESSION, $harness->sessionPayload());
    }

    public function testCloseSessionClearsSessionPayloadAndRegeneratesState(): void
    {
        RuntimeMode::enableSwoole();
        $harness = new SessionTraitHarness();
        $harness->bootSession();
        $_SESSION['foo'] = 'bar';

        $harness->closeSession();

        $this->assertSame([], $_SESSION);
        $this->assertSame([], $harness->sessionPayload());
    }
}

class SessionTraitHarness
{
    use SessionTrait;

    public $user = null;
    public $admin = null;

    public function bootSession(): void
    {
        $this->initSession();
    }

    public function sessionPayload(): array
    {
        return is_array($this->session) ? $this->session : [];
    }
}

