<?php

namespace PSFS\tests\base\extension;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\extension\TemplateFunctions;
use PSFS\base\types\Form;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\GeneratorHelper;

class TemplateFunctionsTestForm extends Form
{
    public function getName()
    {
        return 'template_functions_form';
    }
}

class TemplateFunctionsTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $requestBackup = [];
    private array $getBackup = [];
    private array $cookieBackup = [];
    private array $filesBackup = [];
    private array $cleanupPaths = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER;
        $this->requestBackup = $_REQUEST;
        $this->getBackup = $_GET;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
        $this->bootstrapRequest();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        $_SERVER = $this->serverBackup;
        $_REQUEST = $this->requestBackup;
        $_GET = $this->getBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
    }

    public function testConfigQueryRouteAndSessionFunctions(): void
    {
        Security::getInstance()->setSessionKey('k', 'v');
        Security::getInstance()->setFlash('flash-k', 'flash-v');

        $this->assertSame('v', TemplateFunctions::session('k'));
        $this->assertTrue(TemplateFunctions::existsFlash('flash-k'));
        $this->assertSame('flash-v', TemplateFunctions::getFlash('flash-k'));
        $this->assertFalse(TemplateFunctions::existsFlash('flash-k'));

        $this->assertSame('1', TemplateFunctions::query('a'));
        $this->assertSame('default', TemplateFunctions::config('__missing__', 'default'));
        $this->assertNotEmpty(TemplateFunctions::route('admin'));
        $this->assertSame('/', TemplateFunctions::route('__missing__'));
    }

    public function testCryptoAndTokenHelpers(): void
    {
        $encrypted = TemplateFunctions::encrypt('secret', 'key');
        $this->assertNotSame('secret', $encrypted);
        $this->assertSame('secret', AuthHelper::decrypt($encrypted, 'key'));

        $authToken = TemplateFunctions::generateAuthToken('user', 'pass', 'ua');
        $this->assertNotEmpty($authToken);

        $jwt = TemplateFunctions::generateJWTToken('user', 'module', 'pass');
        $this->assertNotEmpty($jwt);
        $this->assertStringContainsString('.', $jwt);
    }

    public function testResourceAndInternalAssetHelpers(): void
    {
        $source = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'tmp-template-source.txt';
        file_put_contents($source, 'template-functions-source');
        $this->cleanupPaths[] = $source;

        Config::save(array_merge($this->configBackup, ['debug' => false, 'cache.var' => 'vtest']), []);
        Config::getInstance()->loadConfigData(true);

        $this->assertSame('', TemplateFunctions::resource($source, '/docs', true));
        $copied = WEB_DIR . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . basename($source);
        $this->cleanupPaths[] = $copied;
        $this->assertFileExists($copied);

        $extractPathname = new \ReflectionMethod(TemplateFunctions::class, 'extractPathname');
        $extractPathname->setAccessible(true);
        $this->assertSame($source, $extractPathname->invoke(null, $source, []));

        $processAsset = new \ReflectionMethod(TemplateFunctions::class, 'processAsset');
        $processAsset->setAccessible(true);
        $processedPath = $processAsset->invoke(null, '/cache/tmp-template-source.txt', null, true, $source);
        $this->assertIsString($processedPath);
        $this->assertNotSame('', $processedPath);
        $assetCopy = WEB_DIR . DIRECTORY_SEPARATOR . ltrim($processedPath, '/');
        $this->cleanupPaths[] = $assetCopy;
        $this->assertFileExists($assetCopy);
    }

    public function testAssetReturnsEmptyWhenDomainPathCannotBeResolved(): void
    {
        $this->assertSame('', TemplateFunctions::asset('/missing/path.css'));
    }

    public function testButtonWidgetAndFormRenderingFunctions(): void
    {
        $form = new TemplateFunctionsTestForm();
        $form->add('name', ['type' => 'text']);
        $form->build();

        ob_start();
        TemplateFunctions::button(['label' => 'Save']);
        TemplateFunctions::widget(['name' => 'email', 'type' => 'email'], 'Email');
        TemplateFunctions::widget(['name' => 'optional', 'type' => 'text', 'required' => false]);
        TemplateFunctions::form($form);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testInternalCssAndPutResourceContentBranches(): void
    {
        $cssFile = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'tmp-template-style.css';
        file_put_contents($cssFile, 'body{background:url("/img/logo.png")}');
        $this->cleanupPaths[] = $cssFile;

        $processCssLines = new \ReflectionMethod(TemplateFunctions::class, 'processCssLines');
        $processCssLines->setAccessible(true);
        $processCssLines->invoke(null, $cssFile);

        $putResourceContent = new \ReflectionMethod(TemplateFunctions::class, 'putResourceContent');
        $putResourceContent->setAccessible(true);
        $namedTarget = 'tmp-template-target.txt';
        $this->cleanupPaths[] = WEB_DIR . DIRECTORY_SEPARATOR . $namedTarget;
        $putResourceContent->invoke(null, $namedTarget, $cssFile, WEB_DIR . DIRECTORY_SEPARATOR, '/ignored.txt');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . $namedTarget);
    }

    private function bootstrapRequest(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/config',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_GET = ['a' => '1'];
        $_REQUEST = ['a' => '1'];
        $_COOKIE = [];
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();
        Router::getInstance();
        Security::getInstance();
        GeneratorHelper::createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'docs');
    }
}
