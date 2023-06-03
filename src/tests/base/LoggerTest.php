<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;

/**
 * Class DispatcherTest
 * @package PSFS\tests\base
 */
class LoggerTest extends TestCase
{
    /**
     * Test to check if the Logger has been created successful
     * @return Logger
     */
    public function getInstance()
    {
        $instance = Logger::getInstance();

        $this->assertNotNull($instance, 'Logger instance is null');
        $this->assertInstanceOf("\\PSFS\\base\\Logger", $instance, 'Instance is different than expected');
        return $instance;
    }

    /**
     * Test to check if the Singleton pattern works
     * @covers
     */
    public function testSingletonLoggerInstance()
    {
        $instance1 = $this->getInstance();
        $uid1 = Logger::getUid();
        $instance2 = $this->getInstance();
        $uid2 = $instance2->getLogUid();

        $this->assertEquals($instance1, $instance2, 'Singleton instances are not equals');
        $this->assertEquals($uid1, $uid2, 'Singleton instances are not equals');
    }

    /**
     * Test all the functionality for the logger class
     * @covers
     */
    public function testLogFunctions()
    {
        try {
            // Basic log
            Logger::log('Test normal log');
            // Warning log
            Logger::log('Test warning log', LOG_WARNING, [], true);
            // Info log
            Logger::log('Test info log', LOG_INFO, [], true);
            // Error log
            Logger::log('Test error log', LOG_ERR, [], true);
            // Critical log
            Logger::log('Test critical log', LOG_CRIT, [], true);
            // Debug log
            Logger::log('Test debug logs', LOG_DEBUG, [], true);
            // Other logs
            Logger::log('Test other logs', LOG_CRON, [], true);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        } finally {
            $this->assertTrue(true, 'Finished Logger test function');
        }
    }

    /**
     * Test non default logger configurations set
     * @covers
     * @throws GeneratorException
     */
    public function testLogSetup()
    {
        // Add memory logger to test this functionality
        $config = Config::getInstance();
        $this->assertInstanceOf(Config::class, $config, 'Config interface');
        $defaultConfig = $config->dumpConfig();
        Config::save(array_merge($defaultConfig, ['logger.memory' => true, 'logger.phpFire' => true, 'profiling.enable' => true]), []);

        // Create a new logger instance
        $logger = new Logger(['test', true]);
        $this->assertInstanceOf(Logger::class, $logger, 'Logger interface');
        $logger->addLog('Test', \Monolog\Logger::DEBUG);
        $logger = null;
        unset($defaultConfig['logger.memory']);
        Config::save($defaultConfig, []);
    }

}