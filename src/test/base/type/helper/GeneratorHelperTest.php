<?php

namespace PSFS\test\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\DeployHelper;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Class GeneratorHelperTest
 * @package PSFS\test\base\type\helper
 */
class GeneratorHelperTest extends TestCase
{

    /**
     * @throws \PSFS\base\exception\GeneratorException
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
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder not exists');

        GeneratorHelper::clearDocumentRoot();
        // Checks if not exists all the folders
        self::assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder still exists');
        self::assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder still exists');
        self::assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder still exists');
        self::assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder still exists');
    }

    /**
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function testCreateRootDocument()
    {
        GeneratorHelper::createRoot(WEB_DIR, null, true);
        // Checks if exists all the folders
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder not exists');
        self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder not exists');
        self::assertFileExists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale', 'locale folder not exists');

        // Check if base files in the document root exists
        $files = [
            'index.php',
            'browserconfig.xml',
            'crossdomain.xml',
            'humans.txt',
            'robots.txt',
        ];
        foreach ($files as $file) {
            self::assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . $file, $file . ' not exists in html path');
        }
    }

    /**
     * @throws \Exception
     */
    public function testDeployNewVersion()
    {
        $configPrevious = Config::getInstance()->dumpConfig();
        $version = DeployHelper::updateCacheVar();
        $config = Config::getInstance()->dumpConfig();
        self::assertEquals($config[DeployHelper::CACHE_VAR_TAG], $version, 'Cache version are not equals');
        foreach ($config as $key => $value) {
            if ($key !== DeployHelper::CACHE_VAR_TAG) {
                self::assertTrue(array_key_exists($key, $configPrevious), 'Missing key in previous config');
                self::assertEquals($value, $configPrevious[$key], 'Config values are not the same');
            }
        }
        self::assertTrue(abs(count(array_keys($configPrevious)) - count(array_keys($config))) < 2, 'There are more than 1 key different in the config');
    }
}
