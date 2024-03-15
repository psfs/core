<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Class DispatcherTest
 * @package PSFS\tests\base
 */
class ConfigTest extends TestCase
{

    const CONFIG_BACKUP_PATH = CONFIG_DIR . DIRECTORY_SEPARATOR . 'config.json.bak';

    /**
     * Creates an instance of Config
     * @return Config
     * @throws GeneratorException
     */
    private function getInstance()
    {
        $config = Config::getInstance();

        $this->assertNotNull($config, 'Instance not created');
        $this->assertInstanceOf(Config::class, $config, 'Instance different than expected');
        Cache::getInstance()->storeData(self::CONFIG_BACKUP_PATH, $config->dumpConfig(), Cache::JSON, true);
        return $config;
    }

    private function restoreConfig()
    {
        $config = Cache::getInstance()->getDataFromFile(self::CONFIG_BACKUP_PATH, Cache::JSON, true);
        Config::save($config, []);
        FileHelper::deleteDir(self::CONFIG_BACKUP_PATH);
    }

    private function simulateRequiredConfig()
    {
        $config = Config::getInstance();
        $data = [];
        foreach (Config::$required as $key) {
            $data[$key] = uniqid('test', true);
        }
        Config::save($data, []);
        $config->loadConfigData();
    }

    /**
     * Test that checks basic functionality
     * @return array
     * @throws GeneratorException
     */
    public function getBasicConfigUse()
    {
        $config = $this->getInstance();
        $previousConfigData = $config->dumpConfig();
        $config->clearConfig();

        // Check if config can create the config dir
        $dirtmp = uniqid('test', true);
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

        return $previousConfigData;
    }

    /**
     * @return void
     * @throws GeneratorException
     */
    public function testConfigFileFunctions()
    {
        $config = $this->getInstance();

        // Original config data
        $original_data = $this->getBasicConfigUse();

        Config::save($original_data, []);

        $this->assertEquals($original_data, $config->dumpConfig(), 'Missmatch configurations');

        Config::save($original_data, [
            'label' => [uniqid('t', true)],
            'value' => [microtime(true)],
        ]);

        $this->assertNotEquals($original_data, $config->dumpConfig(), 'The same configuration file');

        Config::save($original_data, []);
        $this->restoreConfig();
    }

    /**
     * @return void
     * @throws GeneratorException
     */
    public function testMultipleModuleConfig()
    {
        Config::dropInstance();
        $config = $this->getInstance();

        // Original config data
        $original_data = $config->dumpConfig();
        $test_data = microtime(true);
        Config::save($original_data, [
            'label' => ['test'],
            'value' => [$test_data],
        ]);

        $this->assertEquals(Config::getParam('test'), $test_data, 'The value is not the same');
        $this->assertEquals(Config::getParam('test' . uniqid('t', true), $test_data), $test_data, 'The value is not the same with default value');
        $this->assertEquals(Config::getParam('test', null, 'test'), $test_data, 'The value is not the same without module value');

        $test_data2 = microtime(true);
        $original_data = $config->dumpConfig();
        Config::save($original_data, [
            'label' => ['test.test'],
            'value' => [$test_data2],
        ]);
        $this->assertEquals(Config::getParam('test'), $test_data, 'The value is not the same');
        $this->assertEquals(Config::getParam('test' . uniqid('t', true), $test_data), $test_data, 'The value is not the same with default value');
        $this->assertEquals(Config::getParam('test', null, 'test'), $test_data2, 'The value is not the same with module value');
        $this->assertEquals(Config::getParam('test', null, 'testa'), $test_data, 'The value is not the same with module value and default value');
        $this->assertEquals(Config::getParam('test', $test_data2, 'testa'), $test_data, 'The value is not the same with module value and default value');

        $this->restoreConfig();
    }

    public function testCleaningConfigFiles()
    {
        foreach (Config::$cleanable_config_files as $cleanable_config_file) {
            $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . $cleanable_config_file);
        }
        Config::clearConfigFiles();
        foreach (Config::$cleanable_config_files as $cleanable_config_file) {
            $this->assertFileDoesNotExist(CONFIG_DIR . DIRECTORY_SEPARATOR . $cleanable_config_file);
        }
    }
}
