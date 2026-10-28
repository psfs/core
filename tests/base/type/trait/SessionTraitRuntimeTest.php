<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\traits\Security\SessionTrait;

class SessionTraitRuntimeTest extends TestCase
{
    private string $runtimeBackup = '';
    private array $sessionBackup = [];
    private array $cookieBackup = [];

    protected function setUp(): void
    {
        $this->runtimeBackup = RuntimeMode::getCurrentMode();
        $this->sessionBackup = $_SESSION ?? [];
        $this->cookieBackup = $_COOKIE;
        $_COOKIE = [];
        putenv('PSFS_SESSION_LAZY');
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
        $_COOKIE = $this->cookieBackup;
        putenv('PSFS_SESSION_LAZY');
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

    public function testAVisitorWithoutASessionCookieDoesNotGetASession(): void
    {
        $harness = new SessionTraitHarness();

        $harness->bootSession();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame([], $harness->sessionPayload());
    }

    public function testTheBookkeepingOfTheFrameworkDoesNotCreateASession(): void
    {
        $harness = new SessionTraitHarness();
        $harness->bootSession();

        $harness->setSessionKey('__FLASH_CLEAR__', microtime(true));
        $harness->setSessionKey('lastRequest', ['url' => '/x']);
        $harness->setSessionKey('__CACHE__', ['cache' => 10]);
        $harness->setSessionKey('__PSFS_SESSION_LOCALE_SELECTED__', 'es_ES');
        $harness->setSessionKey('anything', null);
        $harness->updateSession(true);

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(10, $harness->getSessionKey('__CACHE__')['cache'], 'the request can still read what it wrote');
    }

    public function testSomethingToRememberCreatesTheSessionAndKeepsWhatWasCollected(): void
    {
        $harness = new SessionTraitHarness();
        $harness->bootSession();
        $harness->setSessionKey('__CACHE__', ['cache' => 10]);

        $harness->setSessionKey('csrf', 'token-1');

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('token-1', $_SESSION['csrf']);
        $this->assertSame(['cache' => 10], $_SESSION['__CACHE__'], 'what the request had collected is not lost');
    }

    public function testASessionCookieResumesTheSession(): void
    {
        $_COOKIE[session_name()] = 'existingsessionid0123456789abcdef';
        $harness = new SessionTraitHarness();

        $harness->bootSession();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testTheLegacySwitchStartsOneSessionPerRequest(): void
    {
        putenv('PSFS_SESSION_LAZY=false');
        $harness = new SessionTraitHarness();

        $harness->bootSession();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testClosingTheSessionOfAVisitorWithoutOneStartsNothing(): void
    {
        $harness = new SessionTraitHarness();
        $harness->bootSession();

        $harness->closeSession();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame([], $harness->sessionPayload());
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

