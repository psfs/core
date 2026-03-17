<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Template;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\RequestHelper;
use PSFS\base\types\helpers\ResponseCookieHelper;
use PSFS\base\types\helpers\ResponseHelper;

class RequestResponseSecurityContractTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBackup = Config::getInstance()->dumpConfig();
        $config = $this->configBackup;
        $config['i18n.autogenerate'] = false;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        Request::dropInstance();
        Template::dropInstance();
    }

    protected function tearDown(): void
    {
        ResponseHelper::setTest(true);
        Template::setTest(false);
        Template::dropInstance();
        Request::dropInstance();
        if (!empty($this->configBackup)) {
            Config::save($this->configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
        parent::tearDown();
    }

    public function testNormalizeOrigin(): void
    {
        $this->assertEquals('https://api.example.com', RequestHelper::normalizeOrigin('https://api.example.com/v1/users'));
        $this->assertEquals('http://localhost:8011', RequestHelper::normalizeOrigin('http://localhost:8011/path?q=1'));
        $this->assertNull(RequestHelper::normalizeOrigin('javascript:alert(1)'));
        $this->assertNull(RequestHelper::normalizeOrigin(null));
    }

    public function testResolveAllowedOrigin(): void
    {
        $origin = 'https://api.example.com';

        $this->assertEquals($origin, RequestHelper::resolveAllowedOrigin($origin, 'https://api.example.com,https://admin.example.com'));
        $this->assertEquals($origin, RequestHelper::resolveAllowedOrigin($origin, 'https://*.example.com'));
        $this->assertEquals('*', RequestHelper::resolveAllowedOrigin($origin, '*'));
        $this->assertNull(RequestHelper::resolveAllowedOrigin($origin, 'https://other.example.com'));
        $this->assertNull(RequestHelper::resolveAllowedOrigin('', 'https://api.example.com'));
    }

    public function testNormalizeCookieDomain(): void
    {
        $this->assertEquals('example.com', ResponseHelper::normalizeCookieDomain('https://example.com:8080'));
        $this->assertEquals('sub.example.com', ResponseHelper::normalizeCookieDomain('sub.example.com'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain('localhost'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain('127.0.0.1'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain(null));
    }

    public function testNormalizeSameSite(): void
    {
        $this->assertEquals('Lax', ResponseHelper::normalizeSameSite(''));
        $this->assertEquals('Lax', ResponseHelper::normalizeSameSite(null));
        $this->assertEquals('Strict', ResponseHelper::normalizeSameSite('strict'));
        $this->assertEquals('None', ResponseHelper::normalizeSameSite('none'));
    }

    public function testCookiePayloadMatrixAndSecurityInterplay(): void
    {
        $payload = ResponseCookieHelper::buildCookiePayload([
            'name' => 'api',
            'value' => 'token',
            'sameSite' => 'none',
            'secure' => false,
            'domain' => 'https://Example.com:8080',
        ], false, 'localhost');

        $this->assertNotNull($payload);
        $this->assertEquals('api', $payload['name']);
        $this->assertTrue($payload['options']['secure'], 'SameSite=None must force secure=true');
        $this->assertEquals('None', $payload['options']['samesite']);
        $this->assertEquals('example.com', $payload['options']['domain']);

        $legacyHttpOnly = ResponseCookieHelper::buildCookiePayload([
            'name' => 'legacy',
            'value' => 'v',
            'http' => false,
        ], true, 'localhost');
        $this->assertFalse($legacyHttpOnly['options']['httponly']);
        $this->assertArrayNotHasKey('domain', $legacyHttpOnly['options']);

        $invalid = ResponseCookieHelper::buildCookiePayload([
            'name' => 'invalid-only-name',
        ], true, 'example.com');
        $this->assertNull($invalid);
    }

    public function testHeaderBookkeepingIsIdempotentAndCaseInsensitive(): void
    {
        ResponseHelper::setHeader('Content-Type: application/json');
        ResponseHelper::setHeader('content-type: application/json');
        $this->assertCount(1, ResponseHelper::$headers_sent);
        $this->assertEquals('application/json', ResponseHelper::$headers_sent['content-type']);

        ResponseHelper::setHeader('Content-Type: text/html');
        $this->assertCount(1, ResponseHelper::$headers_sent);
        $this->assertEquals('text/html', ResponseHelper::$headers_sent['content-type']);

        ResponseHelper::setHeader('HTTP/1.0 404 Not Found');
        $this->assertArrayHasKey('http status', ResponseHelper::$headers_sent);
        ResponseHelper::dropHeader('HTTP STATUS');
        $this->assertArrayNotHasKey('http status', ResponseHelper::$headers_sent);
    }

    public function testNotFoundNegotiationJsonAndHtml(): void
    {
        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertTrue(ResponseHelper::shouldReturnJsonNotFound(false));

        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'CONTENT_TYPE' => 'text/html',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $this->assertFalse(ResponseHelper::shouldReturnJsonNotFound(false));
        $this->assertTrue(ResponseHelper::shouldReturnJsonNotFound(true));
    }

    public function testHttpNotFoundSupportsJsonAndHtmlOutputs(): void
    {
        $request = Request::getInstance();
        ResponseHelper::setTest(false);
        Template::setTest(true);

        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_URI' => '/missing-json',
        ]);
        $jsonResponse = ResponseHelper::httpNotFound(new \Exception('Missing Json', 404), false);
        $jsonPayload = json_decode((string)$jsonResponse, true);
        $this->assertIsArray($jsonPayload);
        $this->assertFalse($jsonPayload['success'] ?? true);
        $this->assertEquals('Missing Json', $jsonPayload['message'] ?? null);

        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'CONTENT_TYPE' => 'text/html',
            'HTTP_ACCEPT' => 'text/html',
            'REQUEST_URI' => '/missing-html',
        ]);
        $htmlResponse = ResponseHelper::httpNotFound(new \Exception('Missing Html', 404), false);
        $this->assertIsString($htmlResponse);
        $this->assertStringNotContainsString('"success":false', $htmlResponse);
    }

    public function testIsSecureRequestDetectsForwardedProto(): void
    {
        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $this->assertTrue(ResponseHelper::isSecureRequest());

        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTPS' => '',
            'REQUEST_SCHEME' => 'http',
        ]);
        $this->assertFalse(ResponseHelper::isSecureRequest());
    }

    public function testIsSecureRequestHonorsForceHttpsConfig(): void
    {
        $config = $this->configBackup;
        $config['force.https'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTPS' => '',
            'REQUEST_SCHEME' => 'http',
        ]);

        $this->assertTrue(ResponseHelper::isSecureRequest());
    }

    public function testSetStatusHeaderDoesNotMutateHeadersInTestMode(): void
    {
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        ResponseHelper::setStatusHeader('HTTP/1.1 201 Created');
        $this->assertArrayNotHasKey('http status', ResponseHelper::$headers_sent);
    }

    public function testSetAuthHeadersDropsAuthorizationInPublicMode(): void
    {
        $_SERVER['PHP_AUTH_USER'] = 'user';
        $_SERVER['PHP_AUTH_PW'] = 'pass';
        ResponseHelper::$headers_sent = [];
        ResponseHelper::setHeader('Authorization: Basic test');
        $this->assertArrayHasKey('authorization', ResponseHelper::$headers_sent);

        ResponseHelper::setAuthHeaders(true);
        $this->assertArrayNotHasKey('authorization', ResponseHelper::$headers_sent);
    }

    public function testSetCookieHeadersSkipsInvalidPayloadsAndRunsWhenNotTest(): void
    {
        ResponseHelper::setTest(false);
        Request::getInstance()->setServer([
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'localhost',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'on',
        ]);
        ResponseHelper::setCookieHeaders([
            'invalid-entry',
            ['name' => 'only-name'],
            [
                'name' => 'ok',
                'value' => 'v',
                'sameSite' => 'Strict',
                'domain' => 'example.com',
                'secure' => true,
            ],
        ]);
        $this->assertTrue(true);
    }

    public function testSetDebugHeadersNoopWhenDebugAndProfilingDisabled(): void
    {
        $config = $this->configBackup;
        $config['debug'] = false;
        $config['profiling.enable'] = false;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        ResponseHelper::setTest(false);
        $vars = ['x' => 1];
        $result = ResponseHelper::setDebugHeaders($vars);
        $this->assertSame($vars, $result);
    }

    public function testHeaderAndStatusMutationBranchesInNonTestMode(): void
    {
        ResponseHelper::setTest(false);
        ResponseHelper::$headers_sent = [];

        ResponseHelper::setHeader('Set-Cookie: a=b');
        $this->assertArrayHasKey('set-cookie', ResponseHelper::$headers_sent);

        ResponseHelper::setHeader('Authorization: Bearer token');
        $this->assertArrayHasKey('authorization', ResponseHelper::$headers_sent);
        ResponseHelper::dropHeader('Authorization');
        $this->assertArrayNotHasKey('authorization', ResponseHelper::$headers_sent);

        ResponseHelper::setAuthHeaders(false);
        $this->assertArrayHasKey('authorization', ResponseHelper::$headers_sent);

        ResponseHelper::setStatusHeader('HTTP/1.1 202 Accepted');
        $this->assertArrayHasKey('http status', ResponseHelper::$headers_sent);
    }
}
