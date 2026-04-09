<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwooleResponseEmitter;

class SwooleResponseEmitterTest extends TestCase
{
    public function testMergeHeadersKeepsPrimaryAndAddsDistinctSetCookieLines(): void
    {
        $emitter = new SwooleResponseEmitter();

        $merged = $emitter->mergeHeaders(
            ['content-type' => 'application/json'],
            [
                'Content-Type: text/html',
                'Set-Cookie: a=1; Path=/',
                'Set-Cookie: a=1; Path=/',
                'Set-Cookie: b=2; Path=/',
            ]
        );

        $this->assertSame('application/json', $merged['content-type']);
        $this->assertSame(['a=1; Path=/', 'b=2; Path=/'], $merged['set-cookie']);
    }

    public function testResolveStatusCodeReadsStatusHeader(): void
    {
        $emitter = new SwooleResponseEmitter();
        $status = $emitter->resolveStatusCode(['http status' => 'HTTP/1.1 401 Unauthorized'], 200);
        $this->assertSame(401, $status);
    }

    public function testEmitWritesStatusHeadersCookiesAndBody(): void
    {
        $emitter = new SwooleResponseEmitter();
        $response = new SwooleEmitterResponseDouble();

        $emitter->emit($response, 202, [
            'content-type' => 'application/json',
            'www-authenticate' => 'Basic realm="PSFS"',
            'set-cookie' => ['sid=abc; Path=/; HttpOnly; SameSite=Strict'],
            'http status' => 'HTTP/1.1 202 Accepted',
        ], '{"ok":true}');

        $this->assertSame(202, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type'] ?? null);
        $this->assertSame('Basic realm="PSFS"', $response->headers['WWW-Authenticate'] ?? null);
        $this->assertSame('sid', $response->cookies[0]['name'] ?? null);
        $this->assertSame('abc', $response->cookies[0]['value'] ?? null);
        $this->assertSame('{"ok":true}', $response->body);
    }
}

class SwooleEmitterResponseDouble
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
