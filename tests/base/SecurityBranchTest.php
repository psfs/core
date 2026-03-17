<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\ResponseHelper;

/**
 * @runInSeparateProcess
 */
class SecurityBranchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        global $_SESSION;
        $_SESSION = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/config',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_REQUEST = [];
        $_GET = [];
        $_COOKIE = [];
        $_FILES = [];
        Security::dropInstance();
        Request::dropInstance();
        Request::getInstance()->init();
        ResponseHelper::setTest(true);
    }

    public function testReadIdentityFromSessionParsesJsonAndRejectsInvalid(): void
    {
        $security = Security::getInstance(true);
        $admin = ['alias' => 'json-admin', 'profile' => AuthHelper::ADMIN_ID_TOKEN];
        $security->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, json_encode($admin));
        $this->assertSame($admin, $this->invokePrivate($security, 'readIdentityFromSession', [AuthHelper::ADMIN_ID_TOKEN]));

        $security->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, 'not-json-not-serialized');
        $this->assertNull($this->invokePrivate($security, 'readIdentityFromSession', [AuthHelper::ADMIN_ID_TOKEN]));
    }

    public function testCanAccessRestrictedAdminRespectsLoginRouteRule(): void
    {
        $security = Security::getInstance(true);
        $this->setProperty($security, 'admin', ['alias' => 'admin', 'profile' => AuthHelper::ADMIN_ID_TOKEN]);

        $_SERVER['REQUEST_URI'] = '/admin/login';
        Request::dropInstance();
        Request::getInstance()->init();
        $this->assertFalse($security->canAccessRestrictedAdmin());

        $_SERVER['REQUEST_URI'] = '/admin/dashboard';
        Request::dropInstance();
        Request::getInstance()->init();
        $this->assertTrue($security->canAccessRestrictedAdmin());
    }

    public function testCheckAdminShortCircuitWhenAlreadyChecked(): void
    {
        $security = Security::getInstance(true);
        $this->setProperty($security, 'authorized', true);
        $this->setProperty($security, 'checked', true);

        $this->assertTrue($security->checkAdmin(null, null, false));
    }

    public function testAuthorizeAdminCredentialsSetsSessionForValidUser(): void
    {
        $security = Security::getInstance(true);
        $admins = [
            'root' => [
                'hash' => sha1('root:secret'),
                'profile' => AuthHelper::ADMIN_ID_TOKEN,
            ],
        ];

        $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, null, null, null]);
        $this->assertNull($security->getAdmin());

        $token = sha1('root:secret');
        $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', $token, 'secret']);
        $admin = $security->getAdmin();
        $this->assertIsArray($admin);
        $this->assertSame('root', $admin['alias'] ?? null);
        $this->assertSame(AuthHelper::ADMIN_ID_TOKEN, $admin['profile'] ?? null);
    }

    protected function tearDown(): void
    {
        $security = Security::getInstance(true);
        $security->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, null);
        $security->setSessionKey(AuthHelper::USER_ID_TOKEN, null);
        Security::dropInstance();
        Request::dropInstance();
        global $_SESSION;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    private function setProperty(object $instance, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($instance, $value);
    }

    private function invokePrivate(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($instance, $args);
    }
}
