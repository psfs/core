<?php

namespace PSFS\tests\base\type\trait;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
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
    private array $sessionBackup = [];
    private array $singletonRegistryBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
        $this->sessionBackup = $_SESSION ?? [];

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
        $_SESSION = [];
        Request::dropInstance();
        Request::getInstance()->init();
        Security::dropInstance();
        Security::setTest(false);
        Cache::dropInstance();

        ResponseHelper::setTest(true);
        ResponseHelper::$headers_sent = [];
        OutputTraitTestDouble::setTest(false);
        $this->backupSingletonRegistry();
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
        $_SESSION = $this->sessionBackup;
        Request::dropInstance();
        Security::setTest(false);
        Security::dropInstance();
        Cache::dropInstance();
        $this->restoreSingletonRegistry();
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
        $this->assertArrayHasKey('cache-control', ResponseHelper::$headers_sent);
        $cacheControl = ResponseHelper::$headers_sent['cache-control'];
        $this->assertIsArray($cacheControl);
        $this->assertContains('no-store, no-cache, must-revalidate', $cacheControl);
        $this->assertContains('pre-check=0, post-check=0, max-age=0', $cacheControl);
    }

    #[RunInSeparateProcess]
    public function testOutputStoresCachePayloadWhenCacheIsEnabledAndStatusOk(): void
    {
        $config = $this->configBackup;
        $config['debug'] = false;
        $config['cache.data.enable'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        ResponseHelper::setTest(false);

        $security = Security::getInstance(true);
        $security->setSessionKey(Cache::CACHE_SESSION_VAR, [
            'cache' => 300,
            'params' => ['id' => 7],
            'http' => 'GET',
            'slug' => '/output/cache',
            'class' => '\\PSFS\\tests\\base\\type\\trait\\OutputTraitTestDouble',
            'method' => 'output',
            'module' => 'PSFS',
        ]);
        $security->updateSession();

        $cacheSpy = new OutputTraitCacheSpy();
        $cacheSpy->path = 'module/hash/path/';
        $cacheSpy->filename = 'payload-hash';
        $this->injectCacheSpy($cacheSpy);

        $sut = new OutputTraitTestDouble();
        ob_start();
        $sut->output('{"ok":true}', 'application/json');
        ob_end_clean();

        $this->assertTrue($sut->closed);
        $this->assertCount(2, $cacheSpy->storeCalls);
        $this->assertSame('json' . DIRECTORY_SEPARATOR . 'module/hash/path/payload-hash', $cacheSpy->storeCalls[0]['path']);
        $this->assertSame('json' . DIRECTORY_SEPARATOR . 'module/hash/path/payload-hash.headers', $cacheSpy->storeCalls[1]['path']);
        $this->assertFalse($cacheSpy->flushCalled);
    }

    #[RunInSeparateProcess]
    public function testOutputFlushesCacheForNonGetWhenNotCacheableStatus(): void
    {
        $config = $this->configBackup;
        $config['debug'] = false;
        $config['cache.data.enable'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        ResponseHelper::setTest(false);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        Request::dropInstance();
        Request::getInstance()->init();

        $security = Security::getInstance(true);
        $security->setSessionKey(Cache::CACHE_SESSION_VAR, [
            'cache' => 300,
            'params' => [],
            'http' => 'POST',
            'slug' => '/output/flush',
            'class' => '\\PSFS\\tests\\base\\type\\trait\\OutputTraitTestDouble',
            'method' => 'output',
            'module' => 'PSFS',
        ]);
        $security->updateSession();

        $cacheSpy = new OutputTraitCacheSpy();
        $cacheSpy->path = 'module/hash/path/';
        $cacheSpy->filename = 'payload-hash';
        $this->injectCacheSpy($cacheSpy);

        $sut = new OutputTraitTestDouble();
        $sut->setStatus('500');
        ob_start();
        $sut->output('error', 'text/plain');
        ob_end_clean();

        $this->assertTrue($sut->closed);
        $this->assertTrue($cacheSpy->flushCalled);
        $this->assertCount(0, $cacheSpy->storeCalls);
    }

    private function backupSingletonRegistry(): void
    {
        $reflection = new \ReflectionClass(SingletonRegistry::class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $this->singletonRegistryBackup = $property->getValue() ?? [];
    }

    private function restoreSingletonRegistry(): void
    {
        $reflection = new \ReflectionClass(SingletonRegistry::class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $property->setValue(null, $this->singletonRegistryBackup);
    }

    private function injectCacheSpy(OutputTraitCacheSpy $cacheSpy): void
    {
        $reflection = new \ReflectionClass(SingletonRegistry::class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $instances = $property->getValue() ?? [];
        $context = $_SERVER[SingletonRegistry::CONTEXT_SESSION] ?? SingletonRegistry::CONTEXT_SESSION;
        if (!isset($instances[$context]) || !is_array($instances[$context])) {
            $instances[$context] = [];
        }
        $instances[$context][Cache::class] = $cacheSpy;
        $property->setValue(null, $instances);
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

class OutputTraitCacheSpy extends Cache
{
    public string $path = '';
    public string $filename = '';
    public array $storeCalls = [];
    public bool $flushCalled = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function getRequestCacheHash()
    {
        return [$this->path, $this->filename];
    }

    public function storeData($path, $data, $transform = Cache::TEXT, $absolute = false)
    {
        $this->storeCalls[] = [
            'path' => $path,
            'transform' => $transform,
            'absolute' => $absolute,
            'data' => $data,
        ];
    }

    public function flushCache()
    {
        $this->flushCalled = true;
    }
}
