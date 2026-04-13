<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\types\AuthApi;
use PSFS\base\types\helpers\SecurityHelper;

class AuthApiTestDouble extends AuthApi
{
    public bool $admin = false;

    public function getModelTableMap()
    {
        return 'PSFS\\Demo\\ModelTableMap';
    }

    public function isAdmin()
    {
        return $this->admin;
    }
}

class AuthApiTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $cookieBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];
    private array $filesBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER;
        $this->cookieBackup = $_COOKIE;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->filesBackup = $_FILES;
        AuthApiTestDouble::resetTokenTelemetry();
        $this->bootstrapRequest();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        $_SERVER = $this->serverBackup;
        $_COOKIE = $this->cookieBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
        AuthApiTestDouble::resetTokenTelemetry();
    }

    public function testExtractHeaderTokenPrefersServerAndHeaderFallback(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $request = Request::getInstance();

        $token = $this->callPrivate($api, 'extractHeaderToken', [$request]);
        $this->assertSame('token-from-server', $token);

        $request->setServer(['HTTP_X_API_SEC_TOKEN' => '']);
        $this->setRequestHeaders($request, ['X-API-SEC-TOKEN' => 'token-from-header']);
        $token = $this->callPrivate($api, 'extractHeaderToken', [$request]);
        $this->assertSame('token-from-header', $token);
    }

    public function testResolveApiTokenPrecedenceHeaderCookieAndQuery(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $this->setQuery($api, ['API_TOKEN' => 'legacy-token']);
        $_COOKIE['X-API-SEC-TOKEN'] = 'cookie-token';
        Request::dropInstance();
        Request::getInstance()->init();

        $this->assertSame('token-from-server', $this->callPrivate($api, 'resolveApiToken'));

        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => '']);
        $this->assertSame('cookie-token', $this->callPrivate($api, 'resolveApiToken'));

        $_COOKIE = [];
        Request::dropInstance();
        Request::getInstance()->init();
        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => '']);
        Config::save(array_merge($this->configBackup, ['api.query_token.compat' => true]), []);
        Config::getInstance()->loadConfigData(true);
        $this->assertSame('legacy-token', $this->callPrivate($api, 'resolveApiToken'));

        Config::save(array_merge($this->configBackup, ['api.query_token.compat' => false]), []);
        Config::getInstance()->loadConfigData(true);
        $this->assertSame('', $this->callPrivate($api, 'resolveApiToken'));

        $telemetry = AuthApiTestDouble::getTokenTelemetry();
        $this->assertArrayHasKey('header', $telemetry['sources']);
        $this->assertArrayHasKey('cookie', $telemetry['sources']);
        $this->assertArrayHasKey('query_legacy', $telemetry['sources']);
    }

    public function testResolveApiTokenUsesConfiguredCookieName(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        Config::save(array_merge($this->configBackup, ['api.token.cookie' => 'PSFS_API_TOKEN']), []);
        Config::getInstance()->loadConfigData(true);
        $_COOKIE = ['PSFS_API_TOKEN' => 'cookie-token'];
        Request::dropInstance();
        Request::getInstance()->init();
        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => '']);

        $this->assertSame('cookie-token', $this->callPrivate($api, 'resolveApiToken'));
    }

    public function testResolveApiTokenRejectsLegacyQueryByDefault(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $this->setQuery($api, ['API_TOKEN' => 'legacy-token']);
        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => '']);

        $this->assertSame('', $this->callPrivate($api, 'resolveApiToken'));
    }

    public function testResolveApiTokenRejectsMalformedHeaderTokenAndFallsBackToCookie(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $_COOKIE['X-API-SEC-TOKEN'] = 'cookie-token';
        Request::dropInstance();
        Request::getInstance()->init();
        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => "bad token \n"]);

        $this->assertSame('cookie-token', $this->callPrivate($api, 'resolveApiToken'));
        $telemetry = AuthApiTestDouble::getTokenTelemetry();
        $this->assertArrayHasKey('header', $telemetry['invalid']);
        $this->assertArrayHasKey('cookie', $telemetry['sources']);
    }

    public function testCheckAuthAcceptsNoSecretOrAdminBypass(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $this->setQuery($api, []);
        Config::save($this->withoutSecretConfig(), []);
        Config::getInstance()->loadConfigData(true);
        $this->assertTrue($this->callPrivate($api, 'checkAuth'));

        Config::save(array_merge($this->configBackup, ['api.secret' => 'test-secret']), []);
        Config::getInstance()->loadConfigData(true);
        $this->setQuery($api, ['API_TOKEN' => 'invalid']);
        $this->assertFalse($this->callPrivate($api, 'checkAuth'));

        $api->admin = true;
        $this->assertTrue($this->callPrivate($api, 'checkAuth'));
    }

    public function testCheckAuthWithValidTokenAndModuleSpecificSecret(): void
    {
        $api = $this->newAuthApiWithoutConstructor();
        $secret = 'module-secret';
        $token = SecurityHelper::generateToken($secret, 'psfs');
        $this->setQuery($api, ['API_TOKEN' => $token]);
        Config::save(array_merge($this->configBackup, [
            'psfs.api.secret' => $secret,
            'api.query_token.compat' => true,
        ]), []);
        Config::getInstance()->loadConfigData(true);

        Request::getInstance()->setServer(['HTTP_X_API_SEC_TOKEN' => '']);
        $this->assertTrue($this->callPrivate($api, 'checkAuth'));
    }

    private function withoutSecretConfig(): array
    {
        $config = $this->configBackup;
        unset($config['api.secret']);
        unset($config['psfs.api.secret']);
        return $config;
    }

    private function setQuery(AuthApiTestDouble $api, array $query): void
    {
        $property = new \ReflectionProperty($api, 'query');
        $property->setAccessible(true);
        $property->setValue($api, $query);
    }

    private function setRequestHeaders(Request $request, array $headers): void
    {
        $property = new \ReflectionProperty($request, 'header');
        $property->setAccessible(true);
        $property->setValue($request, $headers);
    }

    private function newAuthApiWithoutConstructor(): AuthApiTestDouble
    {
        $reflection = new \ReflectionClass(AuthApiTestDouble::class);
        /** @var AuthApiTestDouble $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        return $instance;
    }

    private function callPrivate(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($instance, $args);
    }

    private function bootstrapRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/demo',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_X_API_SEC_TOKEN' => 'token-from-server',
        ];
        $_COOKIE = [];
        $_GET = [];
        $_REQUEST = [];
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();
    }
}
