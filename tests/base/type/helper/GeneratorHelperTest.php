<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\DeployHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class GeneratorHelperTest
 * @package PSFS\tests\base\type\helper
 */
class GeneratorHelperTest extends TestCase
{

    /**
     * @throws GeneratorException
     */
    public function testStructureFunctions()
    {
        // try to create html folders
        GeneratorHelper::createDir(WEB_DIR);
        GeneratorHelper::createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'css');
        GeneratorHelper::createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'js');
        GeneratorHelper::createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'media');
        GeneratorHelper::createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'font');

        // Checks if exists all the folders
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder not exists');

        GeneratorHelper::clearDocumentRoot();
        // Checks if not exists all the folders
        $this->assertFileDoesNotExist(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder still exists');
        $this->assertFileDoesNotExist(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder still exists');
        $this->assertFileDoesNotExist(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder still exists');
        $this->assertFileDoesNotExist(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder still exists');
    }

    /**
     * @throws GeneratorException
     */
    public function testCreateRootDocument()
    {
        GeneratorHelper::createRoot(WEB_DIR, null, true);
        // Checks if exists all the folders
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder not exists');
        $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder not exists');
        $this->assertFileExists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale', 'locale folder not exists');

        // Check if base files in the document root exists
        $files = [
            'index.php',
            'browserconfig.xml',
            'crossdomain.xml',
            'humans.txt',
            'robots.txt',
        ];
        foreach ($files as $file) {
            $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . $file, $file . ' not exists in html path');
        }
    }

    public function testCreateRootDocumentWritesVerboseMessagesAndRespectsVerifiableFiles(): void
    {
        $tmpRoot = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-coverage-root';
        GeneratorHelper::deleteDir($tmpRoot);
        GeneratorHelper::createDir($tmpRoot);
        file_put_contents($tmpRoot . DIRECTORY_SEPARATOR . 'humans.txt', 'keep');

        $output = new BufferedOutput();
        GeneratorHelper::createRoot($tmpRoot, $output, false);
        $buffer = $output->fetch();

        $this->assertStringContainsString('Start creating html files', $buffer);
        $this->assertStringContainsString('humans.txt already exists', $buffer);
        $this->assertStringContainsString('index.php created successfully', $buffer);
        $this->assertSame('keep', (string)file_get_contents($tmpRoot . DIRECTORY_SEPARATOR . 'humans.txt'));

        GeneratorHelper::deleteDir($tmpRoot);
    }

    public function testHelperValidatesCustomApiNamespacesAndExtractsClassName(): void
    {
        $this->assertSame('Demo', GeneratorHelper::extractClassFromNamespace('Root\\Api\\Demo'));
        $this->assertIsString(GeneratorHelper::getTemplatePath());

        GeneratorHelper::checkCustomNamespaceApi(GeneratorHelperAbstractApiStub::class);

        $this->expectException(GeneratorException::class);
        GeneratorHelper::checkCustomNamespaceApi(GeneratorHelperConcreteApiStub::class);
    }

    public function testHelperRejectsInvalidCustomApiNamespaces(): void
    {
        try {
            GeneratorHelper::checkCustomNamespaceApi(\stdClass::class);
            $this->fail('Expected exception for non API class');
        } catch (GeneratorException $exception) {
            $this->assertStringContainsString('must extend', $exception->getMessage());
        }

        $this->expectException(GeneratorException::class);
        GeneratorHelper::checkCustomNamespaceApi('Non\\Existing\\Api');
    }

    public function testCopyResourcesAndDeleteDirSymlinkAndFailurePath(): void
    {
        $srcDir = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-generator-src';
        $srcFile = $srcDir . DIRECTORY_SEPARATOR . 'hello.txt';
        $targetDest = '/tmp-generator-dest';

        GeneratorHelper::deleteDir($srcDir);
        GeneratorHelper::createDir($srcDir);
        file_put_contents($srcFile, 'hello');

        GeneratorHelper::copyResources($targetDest, false, $srcDir, false);
        $this->assertFileExists(WEB_DIR . $targetDest . DIRECTORY_SEPARATOR . 'tmp-generator-src' . DIRECTORY_SEPARATOR . 'hello.txt');

        $targetFile = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-generator-file-target';
        file_put_contents($targetFile, 'not-a-directory');
        try {
            GeneratorHelper::copyResources('/tmp-generator-file-target', true, $srcFile, false);
            $this->fail('Expected ConfigException when destination parent is a file');
        } catch (ConfigException) {
            $this->assertTrue(true);
        }

        $symlink = WEB_DIR . DIRECTORY_SEPARATOR . 'tmp-generator-link';
        @unlink($symlink);
        symlink($srcDir, $symlink);
        GeneratorHelper::deleteDir($symlink);
        $this->assertFileDoesNotExist($symlink);

        GeneratorHelper::deleteDir(WEB_DIR . $targetDest);
        GeneratorHelper::deleteDir($srcDir);
        @unlink($targetFile);
    }

    /**
     * @throws \Exception
     */
    public function testDeployNewVersion()
    {
        $configPrevious = Config::getInstance()->dumpConfig();
        $version = DeployHelper::updateCacheVar();
        $config = Config::getInstance()->dumpConfig();
        $this->assertEquals($config[DeployHelper::CACHE_VAR_TAG], $version, 'Cache version are not equals');
        foreach ($config as $key => $value) {
            if ($key !== DeployHelper::CACHE_VAR_TAG) {
                $this->assertArrayHasKey($key, $configPrevious, 'Missing key in previous config');
                $this->assertEquals($value, $configPrevious[$key], 'Config values are not the same');
            }
        }
        $this->assertTrue(abs(count(array_keys($configPrevious)) - count(array_keys($config))) < 2, 'There are more than 1 key different in the config');
    }

    /**
     * @throws \Exception
     */
    public function testRefreshCacheStateCleansGeneratedConfigArtifacts()
    {
        $artifacts = [
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json',
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json',
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'routes.meta.json',
        ];
        foreach ($artifacts as $artifact) {
            file_put_contents($artifact, json_encode(['test' => true]));
            $this->assertFileExists($artifact);
        }

        $state = DeployHelper::refreshCacheState();
        $this->assertArrayHasKey('version', $state);
        $this->assertArrayHasKey('config_files_cleaned', $state);
        $this->assertTrue($state['config_files_cleaned']);
        $this->assertSame($state['version'], Config::getParam(DeployHelper::CACHE_VAR_TAG));

        foreach ($artifacts as $artifact) {
            $this->assertFileDoesNotExist($artifact);
        }
    }
}

abstract class GeneratorHelperAbstractApiStub extends \PSFS\base\types\Api
{
    public function getModelTableMap()
    {
        return self::class;
    }
}

class GeneratorHelperConcreteApiStub extends \PSFS\base\types\Api
{
    public function getModelTableMap()
    {
        return self::class;
    }
}
