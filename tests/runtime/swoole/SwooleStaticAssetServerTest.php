<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwooleStaticAssetServer;

class SwooleStaticAssetServerTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER ?? [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testTryServeReturnsFalseForNonStaticOrUnsupportedMethod(): void
    {
        $server = new SwooleStaticAssetServer();
        $response = new SwooleStaticResponseDouble();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/assets/app.css';
        $this->assertFalse($server->tryServe($response));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $this->assertFalse($server->tryServe($response));
    }

    public function testTryServeServesHeadAndGetRequests(): void
    {
        $server = new SwooleStaticAssetServer();
        $response = new SwooleStaticResponseDouble();
        $assetPath = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-static-asset.css';
        file_put_contents($assetPath, 'body{color:red;}');

        try {
            $_SERVER['REQUEST_METHOD'] = 'HEAD';
            $_SERVER['REQUEST_URI'] = '/tmp-static-asset.css';
            $this->assertTrue($server->tryServe($response));
            $this->assertSame(200, $response->statusCode);
            $this->assertSame('', $response->body);
            $this->assertSame('text/css; charset=utf-8', $response->headers['Content-Type'] ?? null);

            $response->body = '';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $this->assertTrue($server->tryServe($response));
            $this->assertSame('body{color:red;}', $response->body);
        } finally {
            @unlink($assetPath);
        }
    }
}

class SwooleStaticResponseDouble
{
    public int $statusCode = 0;
    public array $headers = [];
    public string $body = '';

    public function status(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function end(string $body): void
    {
        $this->body = $body;
    }
}
