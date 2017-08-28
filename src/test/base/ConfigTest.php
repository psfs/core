<?php
    namespace PSFS\test\base;
    use PSFS\base\Cache;
    use PSFS\base\config\Config;
    use PSFS\base\types\helpers\FileHelper;
    use PSFS\base\types\helpers\GeneratorHelper;

    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

    /**
     * Class DispatcherTest
     * @package PSFS\test\base
     */
    class ConfigTest extends \PHPUnit_Framework_TestCase {

        const CONFIG_BACKUP_PATH = CONFIG_DIR . DIRECTORY_SEPARATOR . 'config.json.bak';

        /**
         * Creates an instance of Config
         * @return Config
         */
        private function getInstance()
        {
            $config = Config::getInstance();

            $this->assertNotNull($config, 'Instance not created');
            $this->assertInstanceOf("\\PSFS\\base\\config\\Config", $config, 'Instance different than expected');
            Cache::getInstance()->storeData(self::CONFIG_BACKUP_PATH, $config->dumpConfig(), Cache::JSON, true);
            return $config;
        }

        private function restoreConfig() {
            $config = Cache::getInstance()->getDataFromFile(self::CONFIG_BACKUP_PATH, Cache::JSON, true);
            Config::save($config, []);
            FileHelper::deleteDir(self::CONFIG_BACKUP_PATH);
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
            GeneratorHelper::createDir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);
            $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp, 'Can\'t create test dir');
            @rmdir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);

            // Check if platform is configured
            $this->assertTrue(is_bool($config->getDebugMode()));

            // Check path getters
            $this->assertFileExists(GeneratorHelper::getTemplatePath());

            Config::save([], [
                'label' => ['test'],
                'value' => [true]
            ]);

            $configData = $config->dumpConfig();
            $this->assertNotEmpty($configData, 'Empty configuration');
            $this->assertTrue(is_array($configData), 'Configuration is not an array');

            $configured = $config->isConfigured();
            $this->assertTrue(is_bool($configured) && false === $configured);
            $this->assertTrue(is_bool($config->checkTryToSaveConfig()));

            $this->simulateRequiredConfig();
            $configured = $config->isConfigured();
            $this->assertTrue(is_bool($configured) && true === $configured);

            return $previusConfigData;
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
            $this->restoreConfig();
        }
    }
