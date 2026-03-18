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

    public function testResolveAllowedOriginSupportsArrayAndLegacyRegex(): void
    {
        $origin = 'https://secure.example.com';
        $this->assertSame(
            $origin,
            RequestHelper::resolveAllowedOrigin($origin, ['https://api.example.com', 'https://secure.example.com'])
        );
        $this->assertNull(
            RequestHelper::resolveAllowedOrigin($origin, ['https://api.example.com', 'https://admin.example.com'])
        );

        $this->assertSame($origin, RequestHelper::resolveAllowedOrigin($origin, '/^https:\/\/.*\.example\.com$/'));
        $this->assertNull(RequestHelper::resolveAllowedOrigin('https://evil.test', '/^https:\/\/.*\.example\.com$/'));
    }

    public function testGetCorsHeadersIncludesExtraConfiguredHeadersWithoutDuplicates(): void
    {
        $config = $this->configBackup;
        $config['cors.headers'] = ' X-Foo , Authorization, X-Bar, X-Foo ';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $headers = RequestHelper::getCorsHeaders();
        $this->assertContains('Authorization', $headers);
        $this->assertContains('X-Foo', $headers);
        $this->assertContains('X-Bar', $headers);
        $this->assertSame(1, count(array_filter($headers, static fn($h) => $h === 'X-Foo')));
    }

    public function testCheckCorsSetsHeadersForAllowedOriginOnGet(): void
    {
        $config = $this->configBackup;
        $config['cors.enabled'] = ['https://app.example.com', 'https://admin.example.com'];
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://app.example.com/path',
        ]);

        ResponseHelper::$headers_sent = [];
        RequestHelper::checkCORS();

        $this->assertSame('true', ResponseHelper::$headers_sent['access-control-allow-credentials'] ?? null);
        $this->assertSame('https://app.example.com', ResponseHelper::$headers_sent['access-control-allow-origin'] ?? null);
        $this->assertSame('Origin', ResponseHelper::$headers_sent['vary'] ?? null);
        $this->assertArrayHasKey('access-control-allow-methods', ResponseHelper::$headers_sent);
        $this->assertArrayHasKey('access-control-allow-headers', ResponseHelper::$headers_sent);
        $this->assertArrayNotHasKey('http status', ResponseHelper::$headers_sent);
    }

    public function testCheckCorsSkipsHeadersWhenOriginIsNotAllowed(): void
    {
        $config = $this->configBackup;
        $config['cors.enabled'] = 'https://allowed.example.com';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://blocked.example.com',
        ]);

        ResponseHelper::$headers_sent = [];
        RequestHelper::checkCORS();

        $this->assertArrayNotHasKey('access-control-allow-origin', ResponseHelper::$headers_sent);
        $this->assertArrayNotHasKey('access-control-allow-methods', ResponseHelper::$headers_sent);
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

    public function testHeaderBookkeepingMultiValueDoesNotDuplicateSameLine(): void
    {
        ResponseHelper::setHeader('Set-Cookie: session=a');
        ResponseHelper::setHeader('set-cookie: session=a');
        ResponseHelper::setHeader('Set-Cookie: profile=b');

        $this->assertArrayHasKey('set-cookie', ResponseHelper::$headers_sent);
        $cookies = ResponseHelper::$headers_sent['set-cookie'];
        $this->assertIsArray($cookies);
        $this->assertCount(2, $cookies);
        $this->assertSame('session=a', $cookies[0]);
        $this->assertSame('profile=b', $cookies[1]);
    }

    public function testHeaderBookkeepingSupportsMultiCacheControlWithoutDuplicates(): void
    {
        ResponseHelper::setHeader('Cache-Control: no-store, no-cache, must-revalidate');
        ResponseHelper::setHeader('cache-control: no-store, no-cache, must-revalidate');
        ResponseHelper::setHeader('Cache-Control: pre-check=0, post-check=0, max-age=0');

        $this->assertArrayHasKey('cache-control', ResponseHelper::$headers_sent);
        $cacheControl = ResponseHelper::$headers_sent['cache-control'];
        $this->assertIsArray($cacheControl);
        $this->assertCount(2, $cacheControl);
        $this->assertSame('no-store, no-cache, must-revalidate', $cacheControl[0]);
        $this->assertSame('pre-check=0, post-check=0, max-age=0', $cacheControl[1]);
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

    public function testSetCookieHeadersBookkeepingIsIdempotentWithCookieMatrix(): void
    {
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        Request::getInstance()->setServer([
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'on',
        ]);

        ResponseHelper::setCookieHeaders([
            [
                'name' => 'api',
                'value' => 'token',
                'sameSite' => 'None',
                'secure' => false,
                'httpOnly' => true,
                'path' => '/',
                'domain' => 'example.com',
            ],
            [
                'name' => 'api',
                'value' => 'token',
                'sameSite' => 'None',
                'secure' => false,
                'httpOnly' => true,
                'path' => '/',
                'domain' => 'example.com',
            ],
            [
                'name' => 'admin',
                'value' => 'v2',
                'sameSite' => 'Strict',
                'secure' => true,
                'httpOnly' => true,
                'path' => '/admin',
                'domain' => 'example.com',
            ],
        ]);

        $this->assertArrayHasKey('set-cookie', ResponseHelper::$headers_sent);
        $headers = ResponseHelper::$headers_sent['set-cookie'];
        $this->assertIsArray($headers);
        $this->assertCount(2, $headers);
        $this->assertStringContainsString('api=token', $headers[0]);
        $this->assertStringContainsString('SameSite=None', $headers[0]);
        $this->assertStringContainsString('Secure', $headers[0]);
        $this->assertStringContainsString('HttpOnly', $headers[0]);
        $this->assertStringContainsString('Path=/admin', $headers[1]);
        $this->assertStringContainsString('SameSite=Strict', $headers[1]);
    }

    public function testSetCookieHeadersIsIdempotentAcrossRepeatedInvocations(): void
    {
        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        Request::getInstance()->setServer([
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'on',
        ]);
        $payload = [[
            'name' => 'api',
            'value' => 'token',
            'sameSite' => 'None',
            'secure' => false,
            'httpOnly' => true,
            'path' => '/',
            'domain' => 'example.com',
        ]];

        ResponseHelper::setCookieHeaders($payload);
        ResponseHelper::setCookieHeaders($payload);

        $headers = ResponseHelper::$headers_sent['set-cookie'] ?? [];
        $this->assertIsArray($headers);
        $this->assertCount(1, $headers);
        $this->assertStringContainsString('api=token', $headers[0]);
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
