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

    public function testTryServeSpaFallbackServesTheMountIndexForClientRoutes(): void
    {
        $server = new SwooleStaticAssetServer();
        $response = new SwooleStaticResponseDouble();
        $directory = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-ui-fallback';
        $index = $directory . DIRECTORY_SEPARATOR . 'index.html';
        mkdir($directory, 0777, true);
        file_put_contents($index, '<main>static-ui</main>');

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/tmp-ui-fallback/orders/42';

            $this->assertTrue($server->tryServeSpaFallback($response, '/tmp-ui-fallback'));
            $this->assertSame(200, $response->statusCode);
            $this->assertSame('text/html; charset=utf-8', $response->headers['Content-Type'] ?? null);
            $this->assertSame('<main>static-ui</main>', $response->body);
        } finally {
            @unlink($index);
            @rmdir($directory);
        }
    }

    public function testTryServeSpaFallbackAllowsAConfiguredDocumentRootSymlink(): void
    {
        $server = new SwooleStaticAssetServer();
        $response = new SwooleStaticResponseDouble();
        $sourceDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-ui-linked-source-' . uniqid();
        $mountDirectory = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-ui-linked';
        mkdir($sourceDirectory, 0777, true);
        file_put_contents($sourceDirectory . DIRECTORY_SEPARATOR . 'index.html', '<main>linked-ui</main>');
        symlink($sourceDirectory, $mountDirectory);

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/tmp-ui-linked/client-route';

            $this->assertTrue($server->tryServeSpaFallback($response, '/tmp-ui-linked'));
            $this->assertSame('<main>linked-ui</main>', $response->body);
        } finally {
            @unlink($mountDirectory);
            @unlink($sourceDirectory . DIRECTORY_SEPARATOR . 'index.html');
            @rmdir($sourceDirectory);
        }
    }

    public function testTryServeRejectsSymlinkedAssetsWithoutAnExplicitMount(): void
    {
        $server = new SwooleStaticAssetServer();
        $response = new SwooleStaticResponseDouble();
        $sourceDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-ui-linked-asset-' . uniqid();
        $mountDirectory = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-ui-linked-asset';
        mkdir($sourceDirectory, 0777, true);
        file_put_contents($sourceDirectory . DIRECTORY_SEPARATOR . 'main.js', 'console.log("linked");');
        symlink($sourceDirectory, $mountDirectory);

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/tmp-ui-linked-asset/main.js';

            $this->assertFalse($server->tryServe($response));
            $this->assertTrue($server->tryServe($response, '/tmp-ui-linked-asset'));
            $this->assertSame('console.log("linked");', $response->body);
        } finally {
            @unlink($mountDirectory);
            @unlink($sourceDirectory . DIRECTORY_SEPARATOR . 'main.js');
            @rmdir($sourceDirectory);
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
