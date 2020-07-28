<?php
namespace PSFS\test\base;
use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Logger;

/**
 * Class DispatcherTest
 * @package PSFS\test\base
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

        self::assertNotNull($instance, 'Logger instance is null');
        self::assertInstanceOf("\\PSFS\\base\\Logger", $instance, 'Instance is different than expected');
        return $instance;
    }

    /**
     * Test to check if the Singleton pattern works
     */
    public function testSingletonLoggerInstance()
    {
        $instance1 = $this->getInstance();
        $uid1 = Logger::getUid();
        $instance2 = $this->getInstance();
        $uid2 = $instance2->getLogUid();

        self::assertEquals($instance1, $instance2, 'Singleton instances are not equals');
        self::assertEquals($uid1, $uid2, 'Singleton instances are not equals');
    }

    /**
     * Test all the functionality for the logger class
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
        } catch(\Exception $e) {
            self::assertFalse(true, $e->getMessage());
        } finally {
            self::assertTrue(true, 'Finished Logger test function');
        }
    }

    /**
     * Test non default logger configurations set
     */
    public function testLogSetup()
    {
        // Add memory logger to test this functionality
        $config = Config::getInstance();
        self::assertInstanceOf(Config::class, $config, 'Config interface');
        $defaultConfig = $config->dumpConfig();
        Config::save(array_merge($defaultConfig, ['logger.memory'=>true, 'logger.phpFire'=>true, 'profiling.enable' => true]), []);

        // Create a new logger instance
        $logger = new Logger(['test', true]);
        self::assertInstanceOf(Logger::class, $logger, 'Logger interface');
        $logger->addLog('Test', \Monolog\Logger::DEBUG);
        $logger = null;
        unset($defaultConfig['logger.memory']);
        Config::save($defaultConfig, []);
    }

}