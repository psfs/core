<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwooleRequestHandler;

class SwooleRequestHandlerTest extends TestCase
{
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
