<?php
    namespace PSFS\test\base;
    use PSFS\base\config\Config;

    /**
     * Class DispatcherTest
     * @package PSFS\test\base
     */
    class ConfigTest extends \PHPUnit_Framework_TestCase {

        /**
         * Creates an instance of Config
         * @return Config
         */
        private function getInstance()
        {
            $config = Config::getInstance();

            $this->assertNotNull($config, 'Instance not created');
            $this->assertInstanceOf("\\PSFS\\base\\config\\Config", $config, 'Instance different than expected');
            return $config;
        }

        private function simulateRequiredConfig(){
            $config = Config::getInstance();
            $data = [];
            foreach(Config::$required as $key) {
                $data[$key] = uniqid('test');
            }
            Config::save($data, []);
            $config->loadConfigData();
        }

        /**
         * Test that checks basic functionality
         * @return array
         */
        public function getBasicConfigUse()
        {
            $config = $this->getInstance();
            $previusConfigData = $config->dumpConfig();
            $config->clearConfig();

            // Check if config can create the config dir
            $dirtmp = uniqid('test');
            Config::createDir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);
            $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp, 'Can\'t create test dir');
            @rmdir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);

            // Check if platform is configured
            $this->assertTrue(is_bool($config->getDebugMode()));

            // Check path getters
            $this->assertFileExists($config->getTemplatePath());
            $this->assertFileExists($config->getCachePath());

            Config::save([], [
                'label' => ['test'],
                'value' => [true]
            ]);

            $configData = $config->dumpConfig();
            $this->assertNotEmpty($configData, 'Empty configuration');
            $this->assertTrue(is_array($configData), 'Configuration is not an array');

            $propelParams = $config->getPropelParams();
            $this->assertNotEmpty($propelParams, 'Empty configuration');
            $this->assertTrue(is_array($propelParams), 'Configuration is not an array');

            $configured = $config->isConfigured();
            $this->assertTrue(is_bool($configured) && false === $configured);
            $this->assertTrue(is_bool($config->checkTryToSaveConfig()));

            $this->simulateRequiredConfig();
            $configured = $config->isConfigured();
            $this->assertTrue(is_bool($configured) && true === $configured);

            return $previusConfigData;
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
            $original_data = $this->getBasicConfigUse();

            Config::save($original_data, []);

            $this->assertEquals($original_data, $config->dumpConfig(), 'Missmatch configurations');

            Config::save($original_data, [
                'label' => [uniqid()],
                'value' => [microtime(true)],
            ]);

            $this->assertNotEquals($original_data, $config->dumpConfig(), 'The same configuration file');

            Config::save($original_data, []);
        }
    }
