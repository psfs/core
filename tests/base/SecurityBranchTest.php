<?php

namespace PSFS\tests\base;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\runtime\swoole\SwooleResponseEmitter;

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

        $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', 'wrong-token', 'secret']);
        $this->assertSame('root', $security->getAdmin()['alias'] ?? null);
    }

    public function testAdminAuthenticationRotatesSessionIdOnceAndSwooleEmitsTheNewId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $originalConfig = $configProperty->getValue($config);
        $originalHeaders = ResponseHelper::$headers_sent;
        $previousSessionName = session_name();
        $configProperty->setValue($config, array_merge($originalConfig, ['auth.cookie.secret' => bin2hex(random_bytes(32))]));
        ResponseHelper::$headers_sent = [];
        session_name('PSFSSESSID');
        session_start();
        $_SESSION['preauth-state'] = 'preserved';
        $oldSessionId = session_id();

        try {
            $security = Security::getInstance(true);
            $admins = ['root' => ['hash' => sha1('root:secret'), 'profile' => AuthHelper::ADMIN_ID_TOKEN]];
            $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', sha1('root:secret'), 'secret']);

            $rotatedSessionId = session_id();
            $this->assertNotSame($oldSessionId, $rotatedSessionId);
            $this->assertSame('preserved', $_SESSION['preauth-state'] ?? null);
            $security->updateSession();
            $this->assertSame('root', $_SESSION[AuthHelper::ADMIN_ID_TOKEN]['alias'] ?? null);

            $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', sha1('root:secret'), 'secret']);
            $this->assertSame($rotatedSessionId, session_id());

            $headers = ResponseHelper::$headers_sent;
            (new SwooleResponseEmitter())->ensureSessionCookieHeader($headers);
            $sessionCookie = null;
            foreach ($headers['set-cookie'] ?? [] as $header) {
                if (is_string($header) && str_starts_with($header, 'PSFSSESSID=')) {
                    $sessionCookie = $header;
                    break;
                }
            }
            $this->assertNotNull($sessionCookie);
            $this->assertStringStartsWith('PSFSSESSID=' . $rotatedSessionId, $sessionCookie);
        } finally {
            ResponseHelper::$headers_sent = $originalHeaders;
            $configProperty->setValue($config, $originalConfig);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name($previousSessionName);
        }
    }

    public function testAdminAuthenticationFailsClosedWhenSessionIsInactive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $originalConfig = $configProperty->getValue($config);
        $originalHeaders = ResponseHelper::$headers_sent;
        $previousSessionName = session_name();
        $configProperty->setValue($config, array_merge($originalConfig, ['auth.cookie.secret' => bin2hex(random_bytes(32))]));
        ResponseHelper::$headers_sent = [];
        session_name('PSFSFAIL' . bin2hex(random_bytes(4)));
        session_start();
        $_SESSION = [];

        try {
            $security = Security::getInstance(true);
            $this->assertSame(PHP_SESSION_ACTIVE, session_status());
            session_write_close();

            $admins = ['root' => ['hash' => sha1('root:secret'), 'profile' => AuthHelper::ADMIN_ID_TOKEN]];
            $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', sha1('root:secret'), 'secret']);

            $this->assertNull($security->getAdmin());
            $this->assertNull($security->getSessionKey(AuthHelper::ADMIN_ID_TOKEN));
            $this->assertArrayNotHasKey('set-cookie', ResponseHelper::$headers_sent);
        } finally {
            ResponseHelper::$headers_sent = $originalHeaders;
            $configProperty->setValue($config, $originalConfig);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name($previousSessionName);
        }
    }

    public function testAuthorizeAdminCredentialsEncryptsCookieWithInstallationSecret(): void
    {
        $config = Config::getInstance();
        $property = new \ReflectionProperty(Config::class, 'config');
        $original = $property->getValue($config);
        $originalHeaders = ResponseHelper::$headers_sent;
        $secret = bin2hex(random_bytes(32));
        $property->setValue($config, array_merge($original, ['auth.cookie.secret' => $secret]));
        ResponseHelper::$headers_sent = [];

        try {
            $security = Security::getInstance(true);
            $admins = ['root' => ['hash' => sha1('root:secret'), 'profile' => AuthHelper::ADMIN_ID_TOKEN]];
            $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', sha1('root:secret'), 'secret']);

            $header = ResponseHelper::$headers_sent['set-cookie'][0] ?? '';
            $this->assertStringContainsString(AuthHelper::generateProfileHash() . '=', $header);
            preg_match('/^[^=]+=([^;]+)/', $header, $matches);
            $cookie = rawurldecode($matches[1] ?? '');
            $this->assertStringStartsWith('v2:', $cookie);
            $this->assertSame('root:secret', AuthHelper::decryptCookieCredentials($cookie));
            $this->assertFalse(AuthHelper::decrypt($cookie, AuthHelper::SESSION_TOKEN));
        } finally {
            ResponseHelper::$headers_sent = $originalHeaders;
            $property->setValue($config, $original);
        }
    }

    public function testAuthorizeAdminCredentialsDoesNotWriteCookieWithoutInstallationSecret(): void
    {
        $config = Config::getInstance();
        $property = new \ReflectionProperty(Config::class, 'config');
        $original = $property->getValue($config);
        $originalHeaders = ResponseHelper::$headers_sent;
        $withoutSecret = $original;
        unset($withoutSecret['auth.cookie.secret']);
        $property->setValue($config, $withoutSecret);
        ResponseHelper::$headers_sent = [];

        try {
            $security = Security::getInstance(true);
            $admins = ['root' => ['hash' => sha1('root:secret'), 'profile' => AuthHelper::ADMIN_ID_TOKEN]];
            $this->invokePrivate($security, 'authorizeAdminCredentials', [$admins, 'root', sha1('root:secret'), 'secret']);

            $this->assertArrayNotHasKey('set-cookie', ResponseHelper::$headers_sent);
            $this->assertSame('root', $security->getAdmin()['alias'] ?? null);
        } finally {
            ResponseHelper::$headers_sent = $originalHeaders;
            $property->setValue($config, $original);
        }
    }

    public function testResolveAdminCredentialsUsesJwtWhenEnabled(): void
    {
        $config = Config::getInstance()->dumpConfig();
        $config['enable.jwt'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $subject = 'jwt_security_branch';
        $hash = sha1($subject . ':secret');
        $token = JWT::encode([
            'sub' => $subject,
            'iat' => time() - 10,
            'exp' => time() + 120,
        ], $hash, 'HS256');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        Request::dropInstance();
        Request::getInstance()->init();

        $security = Security::getInstance(true);
        [$user, $tokenHash] = $this->invokePrivate($security, 'resolveAdminCredentials', [[
            $subject => ['hash' => $hash],
        ], null, null]);
        $this->assertSame($subject, $user);
        $this->assertSame($hash, $tokenHash);
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
        $reflection->setValue($instance, $value);
    }

    private function invokePrivate(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        return $reflection->invokeArgs($instance, $args);
    }
}
