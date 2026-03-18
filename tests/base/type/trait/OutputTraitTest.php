<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\OutputTrait;

class OutputTraitTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];
    private array $cookieBackup = [];
    private array $filesBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/output/test',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_GET = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();

        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        OutputTraitTestDouble::setTest(false);
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
        ResponseHelper::setTest(true);
        OutputTraitTestDouble::setTest(false);
    }

    public function testSetStatusMapsKnownAndCustomCodes(): void
    {
        $sut = new OutputTraitTestDouble();
        $sut->setStatus('404');
        $this->assertSame('HTTP/1.0 404 Not Found', $sut->getStatusCode());

        $sut->setStatus('200');
        $this->assertSame('HTTP/1.0 200 OK', $sut->getStatusCode());

        $sut->setStatus('429');
        $this->assertSame('HTTP/1.0 429', $sut->getStatusCode());
    }

    public function testOutputReturnsRawValueInTestMode(): void
    {
        OutputTraitTestDouble::setTest(true);
        $sut = new OutputTraitTestDouble();
        $result = $sut->output('payload', 'application/json');
        $this->assertSame('payload', $result);
        $this->assertFalse($sut->closed);
    }

    public function testOutputSetsResponseHeadersAndTriggersCloseRender(): void
    {
        $config = $this->configBackup;
        $config['debug'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        ResponseHelper::setTest(false);

        $sut = new OutputTraitTestDouble();
        ob_start();
        $sut->output('abc', 'application/json');
        ob_end_clean();

        $this->assertTrue($sut->closed);
        $this->assertSame('PSFS', ResponseHelper::$headers_sent['x-powered-by'] ?? null);
        $this->assertSame('HTTP/1.0 200 OK', ResponseHelper::$headers_sent['http status'] ?? null);
        $this->assertSame('application/json', ResponseHelper::$headers_sent['content-type'] ?? null);
        $this->assertSame('3', ResponseHelper::$headers_sent['content-length'] ?? null);
        $this->assertArrayHasKey('crc', ResponseHelper::$headers_sent);
    }

    public function testRenderCacheSetsCachedHeaderAndTriggersCloseRender(): void
    {
        $sut = new OutputTraitTestDouble();
        ob_start();
        $sut->renderCache('cached-data', ['Content-Type: text/plain']);
        ob_end_clean();

        $this->assertTrue($sut->closed);
        $this->assertSame('text/plain', ResponseHelper::$headers_sent['content-type'] ?? null);
        $this->assertSame('true', ResponseHelper::$headers_sent['x-psfs-cached'] ?? null);
    }

    public function testDownloadSetsAttachmentHeadersAndTriggersCloseRender(): void
    {
        $sut = new OutputTraitTestDouble();
        ob_start();
        $sut->download('file-content', 'text/plain', 'demo.txt');
        ob_end_clean();

        $this->assertTrue($sut->closed);
        $this->assertSame('text/plain', ResponseHelper::$headers_sent['content-type'] ?? null);
        $this->assertSame('attachment; filename="demo.txt"', ResponseHelper::$headers_sent['content-disposition'] ?? null);
        $this->assertSame('demo.txt', ResponseHelper::$headers_sent['filename'] ?? null);
    }
}

class OutputTraitTestDouble
{
    use OutputTrait;

    public bool $closed = false;

    public function closeRender(): void
    {
        $this->closed = true;
    }
}
