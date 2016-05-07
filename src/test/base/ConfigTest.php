<?php
    namespace PSFS\test\base;
    use PSFS\base\config\Config;

    /**
     * Class DispatcherTest
     * @package PSFS\test\base
     */
    class ConfigTest extends \PHPUnit_Framework_TestCase {

        /**
         * Cretes an instance of Config
         * @return Config
         */
        private function getInstance()
        {
            $config = Config::getInstance();

            $this->assertNotNull($config, 'Instance not created');
            $this->assertInstanceOf("\\PSFS\\base\\config\\Config", $config, 'Instance different than expected');
            return $config;
        }

        /**
         * Test that checks basic functionality
         * @return array
         */
        public function testBasicConfigUse()
        {
            $config = $this->getInstance();

            // Check if platform is configured
            $this->assertTrue(is_bool($config->getDebugMode()));

            // Check path getters
            $this->assertFileExists($config->getTemplatePath());
            $this->assertFileExists($config->getCachePath());

            if(!$config->isConfigured()) {
                Config::save(['test' => true], []);
            }

            $configData = $config->dumpConfig();
            $this->assertNotEmpty($configData, 'Empty configuration');
            $this->assertTrue(is_array($configData), 'Configuration is not an array');

            $propelParams = $config->getPropelParams();
            $this->assertNotEmpty($propelParams, 'Empty configuration');
            $this->assertTrue(is_array($propelParams), 'Configuration is not an array');

            return $configData;
        }

        /**
         * Test that checks structure function in config
         */
        public function testStructureFunctions()
        {
            $config = $this->getInstance();

            // try to create html folders
            $config->createDir(WEB_DIR);
            $config->createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'css');
            $config->createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'js');
            $config->createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'media');
            $config->createDir(WEB_DIR . DIRECTORY_SEPARATOR . 'font');

            // Checks if exists all the folders
            $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder not exists');
            $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder not exists');
            $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder not exists');
            $this->assertFileExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder not exists');

            $config->clearDocumentRoot();
            // Checks if not exists all the folders
            $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'css', 'css folder still exists');
            $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'js', 'js folder still exists');
            $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'media', 'media folder still exists');
            $this->assertFileNotExists(WEB_DIR . DIRECTORY_SEPARATOR . 'font', 'font folder still exists');
        }

        public function testConfigFileFunctions()
        {
            $config = $this->getInstance();

            // Original config data
            $data = $this->testBasicConfigUse();

            Config::save($data, []);
            $config->loadConfigData();
            
            $this->assertEquals($data, $config->dumpConfig(), 'Missmatch configurations');

            Config::save($data, [
                'label' => [uniqid()],
                'value' => [microtime(true)],
            ]);
            $config->loadConfigData();

            $this->assertNotEquals($data, $config->dumpConfig(), 'The same configuration file');
        }
    }
