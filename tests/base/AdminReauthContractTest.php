<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\services\AdminServices;

class AdminReauthContractTest extends TestCase
{
    private array $serverBackup = [];
    private array $cookieBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->cookieBackup = $_COOKIE;
        $this->sessionBackup = $_SESSION ?? [];
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        AdminServices::setTest(true);
        Security::setTest(false);
        Security::dropInstance();
        Request::dropInstance();
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_COOKIE = [];
        $_SESSION = [];
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_COOKIE = $this->cookieBackup;
        $_SESSION = $this->sessionBackup;
        ResponseHelper::$headers_sent = [];
        Request::dropInstance();
        Security::dropInstance();
        AdminServices::setTest(false);
    }

    public function testSwitchUserClearsAuthStateAndForcesBasicChallenge(): void
    {
        $security = Security::getInstance();
        $security->updateAdmin('admin', AuthHelper::ADMIN_ID_TOKEN);
        $security->setSessionKey(AuthHelper::USER_ID_TOKEN, ['alias' => 'user']);
        $security->updateSession();

        $message = AdminServices::getInstance()->switchUser();

        $this->assertSame('Restricted area', $message);
        $this->assertNull($security->getAdmin());
        $this->assertNull($security->getUser());
        $this->assertNull($security->getSessionKey(AuthHelper::ADMIN_ID_TOKEN));
        $this->assertNull($security->getSessionKey(AuthHelper::USER_ID_TOKEN));
        $this->assertSame('HTTP/1.1 401 Unauthorized', ResponseHelper::$headers_sent['http status'] ?? null);
        $challenge = ResponseHelper::$headers_sent['www-authenticate'] ?? '';
        $this->assertStringStartsWith(
            'Basic Realm="' . trim(\PSFS\base\config\Config::getInstance()->get('platform.name', 'PSFS')) . ' reauth-',
            $challenge
        );
        $cookies = ResponseHelper::$headers_sent['set-cookie'] ?? [];
        $this->assertIsArray($cookies);
        $this->assertNotEmpty($cookies);
        $this->assertStringStartsWith(AuthHelper::generateProfileHash() . '=', $cookies[0]);
        $this->assertStringContainsString('Expires=', $cookies[0]);
        $this->assertStringContainsString('HttpOnly', $cookies[0]);
    }
}
