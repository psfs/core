<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;

class RequestTest extends TestCase
{
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];
    private array $cookieBackup = [];
    private array $filesBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
        $this->bootstrapRequest();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
    }

    public function testHeaderResolutionFromMapQueryAndServer(): void
    {
        $request = Request::getInstance();
        $this->assertSame('Bearer base', $request->getHeader('AUTHORIZATION'));
        $this->assertSame('from-query', $request->getHeader('X-CUSTOM'));
        $this->assertSame('fallback', $request->getHeader('X-MISSING', 'fallback'));
        $this->assertSame('Bearer direct', $request->getHeader('X-API-SEC-TOKEN'));
    }

    public function testRequestDataExtractionAndCollections(): void
    {
        $request = Request::getInstance();
        $this->assertTrue($request->hasCookies());
        $this->assertTrue($request->hasUpload());
        $this->assertSame('a', $request->get('alpha'));
        $this->assertSame('x', $request->getQuery('q'));
        $this->assertNull($request->getQuery('missing'));
        $this->assertSame('cookie-v', $request->getCookie('token'));
        $this->assertSame(['tmp_name' => '/tmp/php-file', 'name' => 'test.txt'], $request->getFile('upload'));
        $this->assertSame([], $request->getFile('unknown'));
        $this->assertSame('x', $request->getData()['q'] ?? null);
    }

    public function testRootUrlAndPortResolutionUsesHostPortWhenPresent(): void
    {
        $request = Request::getInstance();
        $request->setServer([
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'example.org:9443',
            'SERVER_NAME' => 'example.org',
            'REQUEST_SCHEME' => 'http',
            'HTTPS' => '',
        ]);
        $this->assertSame('http://example.org:9443', $request->getRootUrl());
        $this->assertSame('//example.org:9443', $request->getRootUrl(false));
    }

    public function testFileDetectionAndLanguageHeaderAndTimestamp(): void
    {
        $request = Request::getInstance();
        $request->setServer(['REQUEST_URI' => '/assets/app.js']);
        $this->assertTrue($request->isFile());

        $request->setServer(['REQUEST_URI' => '/admin/config']);
        $this->assertFalse($request->isFile());

        Request::setLanguageHeader('en');
        $this->assertSame('en', Request::header('X-API-LANG'));
        $request->setServer(['REQUEST_TIME_FLOAT' => microtime(true)]);
        $this->assertNotNull(Request::getTimestamp());
    }

    private function bootstrapRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/list',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_AUTHORIZATION' => 'Bearer base',
            'HTTP_X_API_SEC_TOKEN' => 'Bearer direct',
        ];
        $_GET = [
            'q' => 'x',
            'h_x-custom' => 'from-query',
        ];
        $_REQUEST = [
            'alpha' => 'a',
            'q' => 'x',
        ];
        $_COOKIE = [
            'token' => 'cookie-v',
        ];
        $_FILES = [
            'upload' => [
                'tmp_name' => '/tmp/php-file',
                'name' => 'test.txt',
            ],
        ];
        Request::dropInstance();
        Request::getInstance()->init();
    }
}
