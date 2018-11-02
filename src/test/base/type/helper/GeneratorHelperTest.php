<?php
namespace PSFS\test\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\GeneratorHelper;

class GeneratorHelperTest extends TestCase
{

    /**
     * Test that checks structure function in config
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
        $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder still exists');
        $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder still exists');
        $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder still exists');
        $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder still exists');
    }

}