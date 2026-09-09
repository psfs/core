<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\runtime\swoole\SwooleRequestHandler;
use PSFS\runtime\swoole\SwooleRequestExecutor;
use PSFS\runtime\swoole\SwooleRequestHydrator;
use PSFS\runtime\swoole\SwooleRuntimeStateManager;
use PSFS\runtime\swoole\SwooleStaticAssetServer;
use PSFS\runtime\swoole\UiDevelopmentHttpProxyInterface;
use PSFS\runtime\swoole\UiDevelopmentProxyTarget;

class SwooleRequestHandlerTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Security::setTest(true);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        putenv('ADMIN_UI_DEV_UPSTREAM');
        Security::setTest(false);
        Security::dropInstance();
    }

    public function testEmitResponseMapsHeadersAndCookiesAndBody(): void
    {
        $handler = new SwooleRequestHandler();
        $response = new SwooleHandlerResponseDouble();

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

    public function testHandleServesStaticRequestThroughMainEntryPoint(): void
    {
        $handler = new SwooleRequestHandler();
        $request = $this->buildRequest('/tmp-swoole-entry.txt', 'GET');
        $response = new SwooleHandlerResponseDouble();
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

    #[RunInSeparateProcess]
    public function testHandleProcessesNonStaticRouteThroughDispatcherPipeline(): void
    {
        $handler = new SwooleRequestHandler();
        $request = $this->buildRequest('/admin/login', 'GET');
        $response = new SwooleHandlerResponseDouble();

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $handler->handle($request, $response);
        } finally {
            while (ob_get_level() > $bufferLevel) {
                @ob_end_clean();
            }
        }

        $this->assertGreaterThanOrEqual(200, $response->statusCode);
        $this->assertArrayNotHasKey(SwooleRequestHandler::RAW_BODY_SERVER_KEY, $_SERVER);
    }

    public function testHandleRejectsAnUnauthorizedMountedUiRequestAndCleansContext(): void
    {
        Security::setTest(false);
        $state = new SwooleHandlerStateManagerProbe();
        $response = new SwooleHandlerResponseDouble();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2/orders'),
            new SwooleHandlerStaticAssetServerProbe(),
            null,
            new SwooleHandlerExecutorProbe(),
            $state
        );

        $handler->handle(new \stdClass(), $response);

        $this->assertSame(401, $response->statusCode);
        $this->assertSame('Basic Realm="PSFS"', $response->headers['WWW-Authenticate'] ?? null);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    public function testHandleRedirectsAFrontendMountRootAndPreservesTheQueryString(): void
    {
        $state = new SwooleHandlerStateManagerProbe();
        $response = new SwooleHandlerResponseDouble();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2?section=users'),
            new SwooleHandlerStaticAssetServerProbe(),
            null,
            new SwooleHandlerExecutorProbe(),
            $state
        );

        $handler->handle(new \stdClass(), $response);

        $this->assertSame(302, $response->statusCode);
        $this->assertSame('/admin-v2/?section=users', $response->headers['Location'] ?? null);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    public function testHandleServesAStaticAssetFromAFrontendMountBeforeDispatching(): void
    {
        $state = new SwooleHandlerStateManagerProbe();
        $assets = new SwooleHandlerStaticAssetServerProbe(true, false);
        $executor = new SwooleHandlerExecutorProbe();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2/assets/app.js'),
            $assets,
            null,
            $executor,
            $state
        );

        $handler->handle(new \stdClass(), new SwooleHandlerResponseDouble());

        $this->assertSame(['/admin-v2'], $assets->staticMounts);
        $this->assertSame([], $assets->spaMounts);
        $this->assertSame([], $executor->requests);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    public function testHandleUsesSpaFallbackForMountedClientRoutes(): void
    {
        $state = new SwooleHandlerStateManagerProbe();
        $assets = new SwooleHandlerStaticAssetServerProbe(false, true);
        $executor = new SwooleHandlerExecutorProbe();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2/orders/42', 'GET', 'filter=open'),
            $assets,
            null,
            $executor,
            $state
        );

        $handler->handle(new \stdClass(), new SwooleHandlerResponseDouble());

        $this->assertSame(['/admin-v2'], $assets->staticMounts);
        $this->assertSame(['/admin-v2'], $assets->spaMounts);
        $this->assertSame([], $executor->requests);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    public function testHandleReturnsBadGatewayWhenTheUiProxyIsUnavailable(): void
    {
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui.test');
        $state = new SwooleHandlerStateManagerProbe();
        $proxy = new SwooleHandlerProxyProbe(null);
        $response = new SwooleHandlerResponseDouble();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2/assets/app.js'),
            new SwooleHandlerStaticAssetServerProbe(),
            null,
            new SwooleHandlerExecutorProbe(),
            $state,
            null,
            $proxy
        );

        $handler->handle(new \stdClass(), $response);

        $this->assertSame(502, $response->statusCode);
        $this->assertSame('Bad Gateway', $response->body);
        $this->assertSame('/admin-v2/assets/app.js', $proxy->requestUris[0]);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    public function testHandleEmitsTheUiProxyResponseAndItsHeaders(): void
    {
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui.test');
        $state = new SwooleHandlerStateManagerProbe();
        $proxy = new SwooleHandlerProxyProbe([
            'status' => 201,
            'headers' => ['content-type' => 'text/plain', 'x-ui' => 'v2'],
            'body' => 'proxied-ui',
        ]);
        $response = new SwooleHandlerResponseDouble();
        $handler = new SwooleRequestHandler(
            new SwooleHandlerHydratorProbe('/admin-v2/assets/app.js'),
            new SwooleHandlerStaticAssetServerProbe(),
            null,
            new SwooleHandlerExecutorProbe(),
            $state,
            null,
            $proxy
        );

        $handler->handle(new \stdClass(), $response);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('text/plain', $response->headers['Content-Type'] ?? null);
        $this->assertSame('v2', $response->headers['X-Ui'] ?? null);
        $this->assertSame('proxied-ui', $response->body);
        $this->assertSame(['context-id'], $state->cleanups);
    }

    private function buildRequest(string $uri, string $method): object
    {
        return new class($uri, $method) {
            public function __construct(private string $uri, private string $method)
            {
            }

            public array $header = ['host' => 'localhost:8080'];
            public array $get = [];
            public array $post = [];
            public array $cookie = [];
            public array $files = [];

            public function __get(string $name)
            {
                if ($name === 'server') {
                    return [
                        'request_uri' => $this->uri,
                        'request_method' => $this->method,
                        'server_port' => 8080,
                        'remote_addr' => '127.0.0.1',
                    ];
                }
                return null;
            }

            public function rawContent(): string
            {
                return '';
            }
        };
    }
}

class SwooleHandlerResponseDouble
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

class SwooleHandlerHydratorProbe extends SwooleRequestHydrator
{
    public function __construct(
        private readonly string $uri,
        private readonly string $method = 'GET',
        private readonly string $queryString = ''
    ) {
    }

    public function hydrate(object $request): string
    {
        $_SERVER = [
            'REQUEST_URI' => $this->uri,
            'REQUEST_METHOD' => $this->method,
            'QUERY_STRING' => $this->queryString,
        ];

        return 'context-id';
    }
}

class SwooleHandlerStateManagerProbe extends SwooleRuntimeStateManager
{
    /** @var string[] */
    public array $cleanups = [];

    public function resetBeforeRequest(): void
    {
    }

    public function cleanupAfterRequest(string $contextId): void
    {
        $this->cleanups[] = $contextId;
    }
}

class SwooleHandlerStaticAssetServerProbe extends SwooleStaticAssetServer
{
    /** @var string[] */
    public array $staticMounts = [];
    /** @var string[] */
    public array $spaMounts = [];

    public function __construct(
        private readonly bool $serveStatic = false,
        private readonly bool $serveSpa = false
    ) {
    }

    public function tryServe(object $response, ?string $symlinkMount = null): bool
    {
        if ($symlinkMount !== null) {
            $this->staticMounts[] = $symlinkMount;
        }
        return $this->serveStatic;
    }

    public function tryServeSpaFallback(object $response, string $mount): bool
    {
        $this->spaMounts[] = $mount;
        return $this->serveSpa;
    }
}

class SwooleHandlerExecutorProbe extends SwooleRequestExecutor
{
    /** @var string[] */
    public array $requests = [];

    public function execute(string $requestUri): array
    {
        $this->requests[] = $requestUri;
        return [200, 'dispatched'];
    }
}

class SwooleHandlerProxyProbe implements UiDevelopmentHttpProxyInterface
{
    /** @var array<int,array{status:int,headers:array<string,mixed>,body:string}>|null */
    private readonly ?array $result;
    /** @var string[] */
    public array $requestUris = [];

    /** @param array{status:int,headers:array<string,mixed>,body:string}|null $result */
    public function __construct(?array $result)
    {
        $this->result = $result;
    }

    public function forward(UiDevelopmentProxyTarget $target, string $requestUri): ?array
    {
        $this->requestUris[] = $requestUri;
        return $this->result;
    }
}
