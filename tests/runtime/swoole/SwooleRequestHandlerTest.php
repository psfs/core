<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwooleRequestHandler;

class SwooleRequestHandlerTest extends TestCase
{
    private string $sessionNameBackup = 'PHPSESSID';
    private string $sessionIdBackup = '';
    private array $serverBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->sessionNameBackup = session_name();
        $this->sessionIdBackup = session_id();
        $this->serverBackup = $_SERVER ?? [];
        $this->sessionBackup = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if ($this->sessionNameBackup !== '') {
            @session_name($this->sessionNameBackup);
        }
        @session_id($this->sessionIdBackup);
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testEmitResponseMapsHeadersAndCookiesAndBody(): void
    {
        $handler = new SwooleRequestHandler();
        $response = new SwooleResponseDouble();

        $handler->emitResponse($response, 201, [
            'http status' => 'HTTP/1.1 201 Created',
            'content-type' => 'application/json',
            'www-authenticate' => 'Basic Realm="PSFS"',
            'set-cookie' => [
                'token=abc; Path=/; HttpOnly; SameSite=Strict',
            ],
        ], '{"ok":true}');

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type'] ?? null);
        $this->assertSame('Basic Realm="PSFS"', $response->headers['WWW-Authenticate'] ?? null);
        $this->assertCount(1, $response->cookies);
        $this->assertSame('token', $response->cookies[0]['name']);
        $this->assertSame('abc', $response->cookies[0]['value']);
        $this->assertSame('{"ok":true}', $response->body);
    }

    public function testHydrateBasicAuthServerVarsParsesAuthorizationHeader(): void
    {
        $handler = new SwooleRequestHandler();
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['AUTH_TYPE']);
        $method = new \ReflectionMethod($handler, 'hydrateBasicAuthServerVars');
        $method->setAccessible(true);

        $method->invoke($handler, [
            'authorization' => 'Basic ' . base64_encode('demo:secret'),
        ]);

        $this->assertSame('Basic', $_SERVER['AUTH_TYPE'] ?? null);
        $this->assertSame('demo', $_SERVER['PHP_AUTH_USER'] ?? null);
        $this->assertSame('secret', $_SERVER['PHP_AUTH_PW'] ?? null);
    }

    public function testMergeHeadersKeepsNativeSetCookieValues(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'mergeHeaders');
        $method->setAccessible(true);

        $merged = $method->invoke($handler, [
            'content-type' => 'text/html',
        ], [
            'Set-Cookie: PHPSESSID=abc; path=/; HttpOnly',
            'Cache-Control: no-store, no-cache, must-revalidate',
            'Pragma: no-cache',
        ]);

        $this->assertIsArray($merged['set-cookie'] ?? null);
        $this->assertStringContainsString('PHPSESSID=abc', (string)$merged['set-cookie'][0]);
        $this->assertSame('text/html', $merged['content-type'] ?? null);
    }

    public function testEnsureSessionCookieHeaderAddsSessionCookieWhenMissing(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'ensureSessionCookieHeader');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        session_id('session-test-id');
        session_start();
        try {
            $headers = [];
            $method->invokeArgs($handler, [&$headers]);
            $setCookies = $headers['set-cookie'] ?? [];
            $this->assertIsArray($setCookies);
            $this->assertNotEmpty($setCookies);
            $this->assertStringContainsString('PHPSESSID=session-test-id', (string)$setCookies[0]);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    public function testTryServeStaticAssetReturnsTrueForExistingPublicFile(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'tryServeStaticAsset');
        $method->setAccessible(true);
        $response = new SwooleResponseDouble();

        $file = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-swoole-static.txt';
        file_put_contents($file, 'ok-static');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/tmp-swoole-static.txt';

        try {
            $served = $method->invoke($handler, $response);
            $this->assertTrue($served);
            $this->assertSame(200, $response->statusCode);
            $this->assertSame('ok-static', $response->body);
        } finally {
            @unlink($file);
        }
    }

    public function testTryServeStaticAssetReturnsFalseForMissingFile(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'tryServeStaticAsset');
        $method->setAccessible(true);
        $response = new SwooleResponseDouble();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/missing-swoole-static.txt';

        $served = $method->invoke($handler, $response);
        $this->assertFalse($served);
        $this->assertSame('', $response->body);
    }

    public function testTryServeStaticAssetSetsJavascriptContentType(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'tryServeStaticAsset');
        $method->setAccessible(true);
        $response = new SwooleResponseDouble();

        $file = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-swoole-static.js';
        file_put_contents($file, 'window.__ok = true;');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/tmp-swoole-static.js';

        try {
            $served = $method->invoke($handler, $response);
            $this->assertTrue($served);
            $this->assertSame('application/javascript; charset=utf-8', $response->headers['Content-Type'] ?? null);
        } finally {
            @unlink($file);
        }
    }

    public function testSyncSessionIdWithIncomingCookieResetsWhenMissing(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'syncSessionIdWithIncomingCookie');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        session_id('sticky-worker-session');
        $method->invoke($handler, []);

        $this->assertSame('', session_id());
    }

    public function testSyncSessionIdWithIncomingCookieHydratesIncomingValue(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'syncSessionIdWithIncomingCookie');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        $method->invoke($handler, ['PHPSESSID' => 'incoming-session-id']);

        $this->assertSame('incoming-session-id', session_id());
    }

    public function testCleanupRuntimeStateResetsSessionAndContext(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'cleanupRuntimeState');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        session_id('cleanup-session-id');
        session_start();
        $_SESSION = ['foo' => 'bar'];
        $_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY] = '{"foo":"bar"}';
        $_SERVER[\PSFS\base\SingletonRegistry::CONTEXT_SESSION] = 'ctx-1';

        $method->invoke($handler, 'ctx-1');

        $this->assertSame('', session_id());
        $this->assertSame([], $_SESSION);
        $this->assertArrayNotHasKey(SwooleRequestHandler::RAW_BODY_SERVER_KEY, $_SERVER);
        $this->assertArrayNotHasKey(\PSFS\base\SingletonRegistry::CONTEXT_SESSION, $_SERVER);
    }

    public function testResolveStaticMimeTypeFallsBackToOctetStreamWhenUnknown(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'resolveStaticMimeType');
        $method->setAccessible(true);

        $file = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-swoole-static.unknownext';
        file_put_contents($file, 'raw');
        try {
            $mime = $method->invoke($handler, $file);
            $this->assertIsString($mime);
            $this->assertNotSame('', $mime);
        } finally {
            @unlink($file);
        }
    }

    public function testParseCookieLineParsesFlagsAndRejectsMalformedInput(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'parseCookieLine');
        $method->setAccessible(true);

        $cookie = $method->invoke($handler, 'token=abc; Path=/admin; Secure; HttpOnly; SameSite=Strict');
        $this->assertIsArray($cookie);
        $this->assertSame('token', $cookie['name'] ?? null);
        $this->assertSame('/admin', $cookie['path'] ?? null);
        $this->assertTrue($cookie['secure'] ?? false);
        $this->assertTrue($cookie['httponly'] ?? false);
        $this->assertSame('Strict', $cookie['samesite'] ?? null);

        $this->assertNull($method->invoke($handler, 'malformed-cookie-line'));
    }

    public function testHandleServesStaticRequestThroughMainEntryPoint(): void
    {
        $handler = new SwooleRequestHandler();
        $request = new class {
            public array $server = [
                'request_uri' => '/tmp-swoole-entry.txt',
                'request_method' => 'GET',
                'server_port' => 8080,
            ];
            public array $header = [
                'host' => 'localhost:8080',
            ];
            public array $get = [];
            public array $post = [];
            public array $cookie = [];
            public array $files = [];
            public function rawContent(): string
            {
                return '';
            }
        };
        $response = new SwooleResponseDouble();
        $file = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-swoole-entry.txt';
        file_put_contents($file, 'entry-static');

        try {
            $handler->handle($request, $response);
            $this->assertSame(200, $response->statusCode);
            $this->assertSame('entry-static', $response->body);
            $this->assertSame('text/plain; charset=utf-8', $response->headers['Content-Type'] ?? null);
        } finally {
            @unlink($file);
        }
    }

    public function testEnsureSessionCookieHeaderSkipsWhenSessionInactiveOrAlreadyPresent(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'ensureSessionCookieHeader');
        $method->setAccessible(true);

        $headers = [];
        $method->invokeArgs($handler, [&$headers]);
        $this->assertSame([], $headers);

        session_name('PHPSESSID');
        session_id('existing-session');
        session_start();
        try {
            $headers = ['set-cookie' => ['PHPSESSID=existing-session; Path=/']];
            $method->invokeArgs($handler, [&$headers]);
            $this->assertCount(1, $headers['set-cookie']);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    public function testHydrateBasicAuthServerVarsClearsServerOnMalformedAuth(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'hydrateBasicAuthServerVars');
        $method->setAccessible(true);

        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';
        $_SERVER['AUTH_TYPE'] = 'Basic';
        $method->invoke($handler, ['authorization' => 'Bearer token']);
        $this->assertArrayNotHasKey('PHP_AUTH_USER', $_SERVER);
        $this->assertArrayNotHasKey('PHP_AUTH_PW', $_SERVER);
        $this->assertArrayNotHasKey('AUTH_TYPE', $_SERVER);
    }

    public function testMergeHeadersDoesNotDuplicateMultiHeaders(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'mergeHeaders');
        $method->setAccessible(true);

        $merged = $method->invoke($handler, [
            'set-cookie' => ['a=1; Path=/'],
        ], [
            'Set-Cookie: a=1; Path=/',
            'Set-Cookie: b=2; Path=/',
        ]);
        $this->assertSame(['a=1; Path=/', 'b=2; Path=/'], $merged['set-cookie']);
    }

    public function testTryServeStaticAssetReturnsFalseForUnsupportedMethodsAndInvalidPaths(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'tryServeStaticAsset');
        $method->setAccessible(true);
        $response = new SwooleResponseDouble();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/tmp-swoole-static.txt';
        $this->assertFalse($method->invoke($handler, $response));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $this->assertFalse($method->invoke($handler, $response));

        $_SERVER['REQUEST_URI'] = "/bad\0path.js";
        $this->assertFalse($method->invoke($handler, $response));
    }

    public function testTryServeStaticAssetHandlesHeadRequestWithoutBody(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'tryServeStaticAsset');
        $method->setAccessible(true);
        $response = new SwooleResponseDouble();

        $file = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-swoole-head.txt';
        file_put_contents($file, 'head-content');
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/tmp-swoole-head.txt';

        try {
            $this->assertTrue($method->invoke($handler, $response));
            $this->assertSame('', $response->body);
            $this->assertSame('text/plain; charset=utf-8', $response->headers['Content-Type'] ?? null);
        } finally {
            @unlink($file);
        }
    }

    public function testNormalizeOutputHeaderNameReturnsExpectedFormats(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'normalizeOutputHeaderName');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($handler, ''));
        $this->assertSame('WWW-Authenticate', $method->invoke($handler, 'www-authenticate'));
        $this->assertSame('Content-Type', $method->invoke($handler, 'content-type'));
    }

    public function testResolveStatusCodeParsesHeaderAndFallsBackToDefault(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'resolveStatusCode');
        $method->setAccessible(true);

        $this->assertSame(202, $method->invoke($handler, ['http status' => 'HTTP/1.1 202 Accepted'], 200));
        $this->assertSame(200, $method->invoke($handler, ['content-type' => 'text/html'], 200));
    }

    public function testHydrateSuperglobalsPopulatesServerAndPayloadData(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'hydrateSuperglobals');
        $method->setAccessible(true);

        $request = new class {
            public array $server = [
                'request_uri' => '/api/demo?x=1',
                'request_method' => 'POST',
                'query_string' => 'x=1',
                'server_port' => 8080,
                'remote_addr' => '127.0.0.1',
            ];
            public array $header = [
                'host' => 'localhost:8080',
                'content-type' => 'application/json',
                'content-length' => '13',
                'authorization' => 'Basic ZGVtbzpzZWNyZXQ=',
            ];
            public array $get = ['x' => '1'];
            public array $post = ['y' => '2'];
            public array $cookie = ['k' => 'v'];
            public array $files = ['f' => ['name' => 'demo.txt']];
            public function rawContent(): string
            {
                return '{"ok":true}';
            }
        };

        $contextId = $method->invoke($handler, $request);

        $this->assertIsString($contextId);
        $this->assertNotSame('', $contextId);
        $this->assertSame('/api/demo?x=1', $_SERVER['REQUEST_URI'] ?? null);
        $this->assertSame('application/json', $_SERVER['CONTENT_TYPE'] ?? null);
        $this->assertSame('13', $_SERVER['CONTENT_LENGTH'] ?? null);
        $this->assertSame('demo', $_SERVER['PHP_AUTH_USER'] ?? null);
        $this->assertSame('secret', $_SERVER['PHP_AUTH_PW'] ?? null);
        $this->assertSame(['x' => '1'], $_GET);
        $this->assertSame(['y' => '2'], $_POST);
        $this->assertSame(['k' => 'v'], $_COOKIE);
        $this->assertSame(['f' => ['name' => 'demo.txt']], $_FILES);
        $this->assertSame('{"ok":true}', $_SERVER[SwooleRequestHandler::RAW_BODY_SERVER_KEY] ?? null);
    }

    public function testSyncSessionIdWithIncomingCookieClosesActiveSessionAndSetsIncomingId(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'syncSessionIdWithIncomingCookie');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        session_id('active-session-id');
        session_start();
        $_SESSION['foo'] = 'bar';

        $method->invoke($handler, ['PHPSESSID' => 'new-session-id']);

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame('new-session-id', session_id());
    }

    public function testEmitResponseCombinesRepeatedHeaderValues(): void
    {
        $handler = new SwooleRequestHandler();
        $response = new SwooleResponseDouble();

        $handler->emitResponse($response, 200, [
            'cache-control' => ['no-store', 'must-revalidate'],
            'http status' => 'HTTP/1.1 200 OK',
        ], 'ok');

        $this->assertSame('no-store, must-revalidate', $response->headers['Cache-Control'] ?? null);
        $this->assertSame('ok', $response->body);
    }

    public function testEmitResponseSkipsMalformedCookieAndEmptyHeaderName(): void
    {
        $handler = new SwooleRequestHandler();
        $response = new SwooleResponseDouble();

        $handler->emitResponse($response, 200, [
            'set-cookie' => [
                'invalid-cookie-line',
                'session=abc; Path=/; HttpOnly',
            ],
            '' => 'ignored',
        ], 'ok');

        $this->assertSame(1, count($response->cookies));
        $this->assertArrayNotHasKey('', $response->headers);
    }

    public function testHydrateBasicAuthServerVarsRejectsInvalidBase64Payload(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'hydrateBasicAuthServerVars');
        $method->setAccessible(true);

        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';
        $_SERVER['AUTH_TYPE'] = 'Basic';
        $method->invoke($handler, ['authorization' => 'Basic ###']);

        $this->assertArrayNotHasKey('PHP_AUTH_USER', $_SERVER);
        $this->assertArrayNotHasKey('PHP_AUTH_PW', $_SERVER);
        $this->assertArrayNotHasKey('AUTH_TYPE', $_SERVER);
    }

    public function testEnsureSessionCookieHeaderIncludesDomainSecureAndSameSite(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'ensureSessionCookieHeader');
        $method->setAccessible(true);

        session_name('PHPSESSID');
        session_set_cookie_params([
            'lifetime' => 60,
            'path' => '/',
            'domain' => 'example.test',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_id('cookie-flags');
        session_start();
        try {
            $headers = [];
            $method->invokeArgs($handler, [&$headers]);
            $line = (string)(($headers['set-cookie'] ?? [])[0] ?? '');
            $this->assertStringContainsString('Domain=example.test', $line);
            $this->assertStringContainsString('Secure', $line);
            $this->assertStringContainsString('SameSite=Strict', $line);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    public function testParseCookieLineHandlesExpiresAndDomainFragments(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'parseCookieLine');
        $method->setAccessible(true);

        $cookie = $method->invoke(
            $handler,
            'token=abc; Expires=not-a-date; Domain=example.test; Path=; ; HttpOnly'
        );

        $this->assertIsArray($cookie);
        $this->assertSame(0, $cookie['expires'] ?? null);
        $this->assertSame('example.test', $cookie['domain'] ?? null);
        $this->assertSame('/', $cookie['path'] ?? null);
        $this->assertTrue($cookie['httponly'] ?? false);
    }

    public function testMergeHeadersNormalizesSingleAndSkipsBlankNativeHeaders(): void
    {
        $handler = new SwooleRequestHandler();
        $method = new \ReflectionMethod($handler, 'mergeHeaders');
        $method->setAccessible(true);

        $merged = $method->invoke($handler, [
            'set-cookie' => 'single=1; Path=/',
        ], [
            '   ',
            'Set-Cookie: second=2; Path=/',
        ]);

        $this->assertSame(
            ['single=1; Path=/', 'second=2; Path=/'],
            $merged['set-cookie'] ?? []
        );
    }

}

class SwooleResponseDouble
{
    public int $statusCode = 0;
    public array $headers = [];
    public array $cookies = [];
    public string $body = '';

    public function status(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function cookie(
        string $name,
        string $value,
        int $expires,
        string $path,
        string $domain,
        bool $secure,
        bool $httponly,
        string $samesite
    ): void {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ];
    }

    public function end(string $body): void
    {
        $this->body = $body;
    }
}
