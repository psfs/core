<?php
    namespace PSFS\test\base;
    use PHPUnit\Framework\TestCase;
    use PSFS\base\Cache;
    use PSFS\base\config\Config;
    use PSFS\base\types\helpers\FileHelper;
    use PSFS\base\types\helpers\GeneratorHelper;

    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

    /**
     * Class DispatcherTest
     * @package PSFS\test\base
     */
    class ConfigTest extends TestCase {

        const CONFIG_BACKUP_PATH = CONFIG_DIR . DIRECTORY_SEPARATOR . 'config.json.bak';

        /**
         * Creates an instance of Config
         * @return Config
         */
        private function getInstance()
        {
            $config = Config::getInstance();

            self::assertNotNull($config, 'Instance not created');
            self::assertInstanceOf(Config::class, $config, 'Instance different than expected');
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
                $data[$key] = uniqid('test', true);
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
            $dirtmp = uniqid('test', true);
            GeneratorHelper::createDir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);
            self::assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp, 'Can\'t create test dir');
            @rmdir(CONFIG_DIR . DIRECTORY_SEPARATOR . $dirtmp);

            // Check if platform is configured
            self::assertTrue(is_bool($config->getDebugMode()));

            // Check path getters
            self::assertFileExists(GeneratorHelper::getTemplatePath());

            Config::save([], [
                'label' => ['test'],
                'value' => [true]
            ]);

            $configData = $config->dumpConfig();
            self::assertNotEmpty($configData, 'Empty configuration');
            self::assertTrue(is_array($configData), 'Configuration is not an array');

            $configured = $config->isConfigured();
            self::assertTrue(is_bool($configured) && false === $configured);
            self::assertTrue(is_bool($config->checkTryToSaveConfig()));

            $this->simulateRequiredConfig();
            $configured = $config->isConfigured();
            self::assertTrue(is_bool($configured) && true === $configured);

            return $previusConfigData;
        }

        public function testConfigFileFunctions()
        {
            $config = $this->getInstance();

            // Original config data
            $original_data = $this->getBasicConfigUse();

            Config::save($original_data, []);

            self::assertEquals($original_data, $config->dumpConfig(), 'Missmatch configurations');

            Config::save($original_data, [
                'label' => [uniqid('t', true)],
                'value' => [microtime(true)],
            ]);

            self::assertNotEquals($original_data, $config->dumpConfig(), 'The same configuration file');

            Config::save($original_data, []);
            $this->restoreConfig();
        }

        public function testMultipleModuleConfig() {
            if(PHP_MAJOR_VERSION === 7) {
                Config::dropInstance();
                $config = $this->getInstance();

                // Original config data
                $original_data = $config->dumpConfig();
                $test_data = microtime(true);
                Config::save($original_data, [
                    'label' => ['test'],
                    'value' => [$test_data],
                ]);

                self::assertEquals(Config::getParam('test'), $test_data, 'The value is not the same');
                self::assertEquals(Config::getParam('test' . uniqid('t', true), $test_data), $test_data, 'The value is not the same with default value');
                self::assertEquals(Config::getParam('test', null, 'test'), $test_data, 'The value is not the same without module value');

                $test_data2 = microtime(true);
                $original_data = $config->dumpConfig();
                Config::save($original_data, [
                    'label' => ['test.test'],
                    'value' => [$test_data2],
                ]);
                self::assertEquals(Config::getParam('test'), $test_data, 'The value is not the same');
                self::assertEquals(Config::getParam('test' . uniqid('t', true), $test_data), $test_data, 'The value is not the same with default value');
                self::assertEquals(Config::getParam('test', null, 'test'), $test_data2, 'The value is not the same with module value');
                self::assertEquals(Config::getParam('test', null, 'testa'), $test_data, 'The value is not the same with module value and default value');
                self::assertEquals(Config::getParam('test', $test_data2, 'testa'), $test_data, 'The value is not the same with module value and default value');

                $this->restoreConfig();
            }
        }
    }
