<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\UiDevelopmentHttpProxy;
use PSFS\runtime\swoole\UiDevelopmentProxyTarget;

class UiDevelopmentHttpProxyTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testForwardBuildsTheRequestAndFiltersHopByHopResponseHeaders(): void
    {
        $client = new UiDevelopmentHttpClientProbe(true, 201, [
            'X-Ui' => 'v2',
            'Connection' => 'close',
            'Set-Cookie' => 'upstream=secret',
        ], 'ui-response');
        $proxy = new UiDevelopmentHttpProxy(static fn (): UiDevelopmentHttpClientProbe => $client);
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_X_REQUEST_ID' => 'request-1',
            'HTTP_CONNECTION' => 'keep-alive',
            'HTTP_AUTHORIZATION' => 'must-not-forward',
            'HTTP_COOKIE' => 'must-not-forward',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_HOST' => 'core.test',
            'REQUEST_SCHEME' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
            'PSFS_RAW_BODY' => '{"id":1}',
        ];

        $result = $proxy->forward(
            new UiDevelopmentProxyTarget('/admin-v2', 'http://ui.test:4200'),
            '/admin-v2/orders?filter=open'
        );

        self::assertSame(201, $result['status']);
        self::assertSame(['x-ui' => 'v2'], $result['headers']);
        self::assertSame('ui-response', $result['body']);
        self::assertSame('POST', $client->method);
        self::assertSame('/admin-v2/orders?filter=open', $client->uri);
        self::assertSame('{"id":1}', $client->data);
        self::assertSame('request-1', $client->requestHeaders['X-Request-Id']);
        self::assertSame('ui.test:4200', $client->requestHeaders['Host']);
        self::assertSame('https', $client->requestHeaders['X-Forwarded-Proto']);
        self::assertTrue($client->closed);
    }

    public function testForwardReturnsNullAndClosesTheClientWhenTheUpstreamFails(): void
    {
        $client = new UiDevelopmentHttpClientProbe(false);
        $proxy = new UiDevelopmentHttpProxy(static fn (): UiDevelopmentHttpClientProbe => $client);

        $result = $proxy->forward(
            new UiDevelopmentProxyTarget('/ui', 'http://ui.test'),
            '/ui/assets/app.js'
        );

        self::assertNull($result);
        self::assertTrue($client->closed);
    }
}

class UiDevelopmentHttpClientProbe
{
    public array $requestHeaders = [];
    public string $method = '';
    public string $uri = '';
    public string $data = '';
    public bool $closed = false;

    public function __construct(
        private readonly bool $executeResult,
        public int $statusCode = -1,
        public array $responseHeaders = [],
        public string $body = ''
    ) {
    }

    public function set(array $settings): void
    {
    }

    public function setHeaders(array $headers): void
    {
        $this->requestHeaders = $headers;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function execute(string $uri): bool
    {
        $this->uri = $uri;
        return $this->executeResult;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'headers' => $this->responseHeaders,
            'body' => $this->body,
            default => null,
        };
    }
}
