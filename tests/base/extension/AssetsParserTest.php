<?php

namespace PSFS\tests\base\extension;

use MatthiasMullie\Minify\JS;
use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\extension\AssetsParser;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\GeneratorHelper;

class AssetsParserTestProxy extends AssetsParser
{
    public function exposeGetSriHash(string $hash, string $type = 'js'): string
    {
        return $this->getSriHash($hash, $type);
    }

    public function exposePrintJs(array $compiledFiles, string $baseUrl, string $hash): string
    {
        ob_start();
        $this->printJs($compiledFiles, $baseUrl, $hash);
        return (string)ob_get_clean();
    }

    public function exposePrintCss(array $compiledFiles, string $baseUrl, string $hash): string
    {
        ob_start();
        $this->printCss($compiledFiles, $baseUrl, $hash);
        return (string)ob_get_clean();
    }

    public function exposePutDebugJs(
        array $pathParts,
        string $base,
        string $file,
        string $hash,
        array &$compiledFiles
    ): string|false {
        return $this->putDebugJs($pathParts, $base, $file, $hash, $compiledFiles);
    }

    public function exposeCompileCss(string $basePath, string $hash): self
    {
        return $this->compileCss($basePath, $hash);
    }

    public function exposeCompileJs(array $files, string $basePath, string $hash, array &$compiledFiles): self
    {
        return $this->compileJs($files, $basePath, $hash, $compiledFiles);
    }

    public function exposeProcessCssLine(string $file, string $base, string $data, string $hash): string|false
    {
        return $this->processCssLine($file, $base, $data, $hash);
    }

    public function exposeExtractCssResources(array $source, string $file): void
    {
        $this->extractCssResources($source, $file);
    }

    public function exposeDumpJs(string $hash, string $base, JS $minifier): void
    {
        $this->dumpJs($hash, $base, $minifier);
    }

    public function exposeSetFiles(array $files): void
    {
        $ref = new \ReflectionProperty(AssetsParser::class, 'files');
        $ref->setAccessible(true);
        $ref->setValue($this, $files);
    }
}

class AssetsParserTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $requestBackup = [];
    private array $getBackup = [];
    private array $cookieBackup = [];
    private array $filesBackup = [];
    private array $cleanupFiles = [];
    private array $cleanupDirs = [];

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
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        // Delete dirs from deepest to shallowest.
        rsort($this->cleanupDirs);
        foreach ($this->cleanupDirs as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
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

    public function testAddFileCompileAndPrintHtmlForJsAndCss(): void
    {
        $this->setDebug(false);
        $assetDir = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-assets';
        GeneratorHelper::createDir($assetDir);
        $this->cleanupDirs[] = $assetDir;

        $jsFile = $assetDir . DIRECTORY_SEPARATOR . 'main.js';
        $cssFile = $assetDir . DIRECTORY_SEPARATOR . 'main.css';
        file_put_contents($jsFile, 'window.__assetTest = 1;');
        file_put_contents($cssFile, 'body{color:#111;}');
        $this->cleanupFiles[] = $jsFile;
        $this->cleanupFiles[] = $cssFile;

        $parserJs = new AssetsParser('js');
        $parserJs->setHash('assets-js-test');
        $parserJs->addFile('tmp-assets/main.js');
        $parserJs->compile();
        ob_start();
        $parserJs->printHtml();
        $outputJs = ob_get_clean();
        $this->assertStringContainsString('/js/assets-js-test', $outputJs);
        $this->cleanupFiles[] = WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'assets-js-test.js';

        $parserCss = new AssetsParser('css');
        $parserCss->setHash('assets-css-test');
        $parserCss->addFile('tmp-assets/main.css');
        $parserCss->compile();
        ob_start();
        $parserCss->printHtml();
        $outputCss = ob_get_clean();
        $this->assertStringContainsString('/css/assets-css-test', $outputCss);
        $this->cleanupFiles[] = WEB_DIR . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'assets-css-test.css';
    }

    public function testDebugPrintAndPutDebugJsPaths(): void
    {
        $this->setDebug(true);
        $assetDir = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-assets-debug';
        GeneratorHelper::createDir($assetDir);
        $this->cleanupDirs[] = $assetDir;

        $jsFile = $assetDir . DIRECTORY_SEPARATOR . 'debug.js';
        $cssFile = $assetDir . DIRECTORY_SEPARATOR . 'debug.css';
        file_put_contents($jsFile, 'console.log("debug");');
        file_put_contents($cssFile, 'body{background:#fff;}');
        $this->cleanupFiles[] = $jsFile;
        $this->cleanupFiles[] = $cssFile;

        $parserJs = new AssetsParserTestProxy('js');
        $compiled = [];
        $pathParts = explode('/', $jsFile);
        $content = $parserJs->exposePutDebugJs(
            $pathParts,
            WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR,
            $jsFile,
            'assets-debug-js',
            $compiled
        );
        $this->assertNotFalse($content);
        $this->assertNotEmpty($compiled);
        $this->cleanupFiles[] = WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'assets-debug-js_' . basename($jsFile);

        $debugJsOutput = $parserJs->exposePrintJs(['/js/assets-debug-js_debug.js'], '', 'assets-debug-js');
        $this->assertStringContainsString('/js/assets-debug-js_debug.js', $debugJsOutput);
        $prodJsOutput = $parserJs->exposePrintJs([], 'http://localhost:8080', 'assets-debug-js');
        $this->assertStringContainsString('/js/assets-debug-js.js', $prodJsOutput);

        $parserCss = new AssetsParserTestProxy('css');
        $debugCssOutput = $parserCss->exposePrintCss(['/css/assets-debug-css_debug.css'], '', 'assets-debug-css');
        $this->assertStringContainsString('/css/assets-debug-css_debug.css', $debugCssOutput);
        $prodCssOutput = $parserCss->exposePrintCss([], 'http://localhost:8080', 'assets-debug-css');
        $this->assertStringContainsString('/css/assets-debug-css.css', $prodCssOutput);
    }

    public function testAddFileIgnoresNonMatchingExtensionsAndMissingFiles(): void
    {
        $parser = new AssetsParser('js');
        $parser->addFile('tmp-assets/missing.js');
        $parser->addFile('tmp-assets/file.css');
        $ref = new \ReflectionProperty(AssetsParser::class, 'files');
        $ref->setAccessible(true);
        $this->assertSame([], $ref->getValue($parser));
    }

    public function testCalculateResourcePathnameRemovesQueryAndAnchor(): void
    {
        $baseDir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'asset-parser';
        GeneratorHelper::createDir($baseDir);
        $this->cleanupDirs[] = $baseDir;
        $cssFile = $baseDir . DIRECTORY_SEPARATOR . 'test.css';
        $imgFile = $baseDir . DIRECTORY_SEPARATOR . 'logo.png';
        file_put_contents($cssFile, 'body{}');
        file_put_contents($imgFile, 'PNG');
        $this->cleanupFiles[] = $cssFile;
        $this->cleanupFiles[] = $imgFile;

        $method = new \ReflectionMethod(AssetsParser::class, 'calculateResourcePathname');
        $method->setAccessible(true);
        $resolved = $method->invoke(null, $cssFile, [0 => "url('logo.png?v=1#x')", 1 => "'logo.png?v=1#x'"]);
        $this->assertSame(realpath($imgFile), $resolved);
    }

    public function testExtractCssLineResourceCopiesReferencedFile(): void
    {
        $publicDir = BASE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'public';
        $fixtureDir = $publicDir . DIRECTORY_SEPARATOR . 'tmp-assets-res';
        $imgDir = $fixtureDir . DIRECTORY_SEPARATOR . 'img';
        GeneratorHelper::createDir($imgDir);
        $this->cleanupDirs[] = $imgDir;
        $this->cleanupDirs[] = $fixtureDir;

        $img = $imgDir . DIRECTORY_SEPARATOR . 'logo.png';
        $css = $fixtureDir . DIRECTORY_SEPARATOR . 'style.css';
        file_put_contents($img, 'fakepng');
        file_put_contents($css, "body{background:url('img/logo.png');}");
        $this->cleanupFiles[] = $img;
        $this->cleanupFiles[] = $css;

        $handle = fopen($css, 'r');
        $this->assertIsResource($handle);
        AssetsParser::extractCssLineResource($handle, $css);
        fclose($handle);

        $copied = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-assets-res' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png';
        $this->cleanupFiles[] = $copied;
        $this->cleanupDirs[] = dirname($copied);
        $this->assertFileExists($copied);
    }

    public function testExtractCssLineResourceAllowsMissingOriginWithoutThrowing(): void
    {
        $baseDir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'asset-parser-missing';
        GeneratorHelper::createDir($baseDir);
        $this->cleanupDirs[] = $baseDir;
        $css = $baseDir . DIRECTORY_SEPARATOR . 'style.css';
        file_put_contents($css, "body{background:url('missing.png');}");
        $this->cleanupFiles[] = $css;

        $handle = fopen($css, 'r');
        $this->assertIsResource($handle);
        AssetsParser::extractCssLineResource($handle, $css);
        fclose($handle);
        $this->assertTrue(true);
    }

    public function testSriHashIsGeneratedAndThenReadFromCache(): void
    {
        $this->setDebug(false);
        $hash = 'sri-assets-test';
        $jsPath = WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $hash . '.js';
        GeneratorHelper::createDir(dirname($jsPath));
        file_put_contents($jsPath, 'console.log("sri");');
        $this->cleanupFiles[] = $jsPath;

        $sriFile = CACHE_DIR . DIRECTORY_SEPARATOR . 'js.sri.json';
        if (file_exists($sriFile)) {
            $backup = $sriFile . '.bak.' . uniqid();
            rename($sriFile, $backup);
            $this->cleanupFiles[] = $backup;
            $this->cleanupFiles[] = $sriFile;
        }

        $parser = new AssetsParserTestProxy('js');
        $parser->init('js');
        $first = $parser->exposeGetSriHash($hash, 'js');
        $second = $parser->exposeGetSriHash($hash, 'js');

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
        $this->assertFileExists($sriFile);
    }

    public function testCompileCssProductionBranchGeneratesCombinedFile(): void
    {
        $this->setDebug(false);
        $hash = 'css-prod-' . uniqid();
        $cssSource = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $hash . '-source.css';
        file_put_contents($cssSource, 'body { color: #123456; }');
        $this->cleanupFiles[] = $cssSource;

        $parser = new AssetsParserTestProxy('css');
        $parser->setHash($hash);
        $parser->exposeSetFiles([$cssSource]);
        $parser->exposeCompileCss(WEB_DIR . DIRECTORY_SEPARATOR, $hash);

        $target = WEB_DIR . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . $hash . '.css';
        $this->cleanupFiles[] = $target;
        $this->assertFileExists($target);
        $this->assertStringContainsString('color', (string)file_get_contents($target));
    }

    public function testProcessCssLineThrowsWhenCombinedFileCannotBeDeleted(): void
    {
        $this->setDebug(true);
        $hash = 'css-unlink-' . uniqid();
        $base = WEB_DIR . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR;
        GeneratorHelper::createDir($base);
        $source = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $hash . '.css';
        file_put_contents($source, 'body{margin:0}');
        $this->cleanupFiles[] = $source;

        $combinedPath = $base . $hash . '.css';
        GeneratorHelper::createDir($combinedPath);
        $this->cleanupDirs[] = $combinedPath;

        $parser = new AssetsParserTestProxy('css');
        $parser->setHash($hash);
        $this->expectException(ConfigException::class);
        $parser->exposeProcessCssLine($source, $base, '', $hash);
    }

    public function testExtractCssResourcesCatchesAtomicCopyFailures(): void
    {
        $this->setDebug(false);
        $publicDir = BASE_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'public';
        $fixtureDir = $publicDir . DIRECTORY_SEPARATOR . 'tmp-assets-copy-fail';
        $imgDir = $fixtureDir . DIRECTORY_SEPARATOR . 'img';
        GeneratorHelper::createDir($imgDir);
        $this->cleanupDirs[] = $imgDir;
        $this->cleanupDirs[] = $fixtureDir;

        $img = $imgDir . DIRECTORY_SEPARATOR . 'logo.png';
        $css = $fixtureDir . DIRECTORY_SEPARATOR . 'style.css';
        file_put_contents($img, 'fakepng');
        file_put_contents($css, "body{background:url('img/logo.png');}");
        $this->cleanupFiles[] = $img;
        $this->cleanupFiles[] = $css;

        $destAsDir = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-assets-copy-fail' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png';
        GeneratorHelper::createDir($destAsDir);
        $this->cleanupDirs[] = $destAsDir;
        @touch($img, time() + 2);

        $parser = new AssetsParserTestProxy('css');
        $parser->exposeExtractCssResources([0 => "url('img/logo.png')", 1 => "'img/logo.png'"], $css);
        $this->assertTrue(true);
    }

    public function testCompileJsProductionAndDumpJsWithAndWithoutObfuscate(): void
    {
        $this->setDebug(false);
        $hash = 'js-prod-' . uniqid();
        $jsSource = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $hash . '.js';
        file_put_contents($jsSource, 'window.__prod=1;');
        $this->cleanupFiles[] = $jsSource;

        $parser = new AssetsParserTestProxy('js');
        $compiled = [];
        $parser->exposeCompileJs([$jsSource], WEB_DIR . DIRECTORY_SEPARATOR, $hash, $compiled);
        $minified = WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $hash . '.js';
        $this->cleanupFiles[] = $minified;
        $this->assertFileExists($minified);

        $config = Config::getInstance()->dumpConfig();
        $config['assets.obfuscate'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $hashGzip = 'js-gzip-' . uniqid();
        $gzipTarget = WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $hashGzip . '.js';
        $this->cleanupFiles[] = $gzipTarget;
        $minifier = new JS();
        $minifier->add('window.__gzip=1;');
        $parser->exposeDumpJs($hashGzip, WEB_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR, $minifier);
        $this->assertFileExists($gzipTarget);
    }

    private function setDebug(bool $debug): void
    {
        $config = Config::getInstance()->dumpConfig();
        $config['debug'] = $debug;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        Config::getInstance()->setDebugMode($debug);
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
        $_GET = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();
    }
}
