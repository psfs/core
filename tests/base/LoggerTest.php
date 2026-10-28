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
            Logger::log('Test alert logs', LOG_ALERT, [], true);
            Logger::log('Test emergency logs', LOG_EMERG, [], true);
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
     * @throws GeneratorException
     */
    public function testLogSetup()
    {
        // Enable profiling to cover the debug stats path
        $config = Config::getInstance();
        $this->assertInstanceOf(Config::class, $config, 'Config interface');
        $defaultConfig = $config->dumpConfig();
        Config::save(array_merge($defaultConfig, ['profiling.enable' => true]), []);

        // Create a new logger instance
        $logger = new Logger(['test', true]);
        $this->assertInstanceOf(Logger::class, $logger, 'Logger interface');
        $logger->addLog('Test', \Monolog\Level::Debug);
        $logger = null;
        Config::save($defaultConfig, []);
    }

    public function testLoggerSupportsStdoutOutputTarget(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $defaultConfig = $configProperty->getValue($config);
        $overrideConfig = $defaultConfig;
        $overrideConfig['log.output'] = 'stdout';
        $configProperty->setValue($config, $overrideConfig);

        try {
            $reflection = new \ReflectionClass(Logger::class);
            /** @var Logger $logger */
            $logger = $reflection->newInstanceWithoutConstructor();

            $method = new \ReflectionMethod(Logger::class, 'resolveLogOutputTarget');
            [$target, $closeStreamOnDestruct] = $method->invoke($logger);

            $this->assertSame('php://stdout', $target);
            $this->assertFalse($closeStreamOnDestruct);
        } finally {
            Logger::dropInstance();
            $configProperty->setValue($config, $defaultConfig);
        }
    }

    public function testChannelLevelControlsWhatReachesTheLogFile(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $defaultConfig = $configProperty->getValue($config);
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-log-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $configProperty->setValue($config, array_merge($defaultConfig, [
            'log.output' => 'file',
            'default.log.path' => $dir,
            'log.level' => 'DEBUG',
            'log.level.cache' => 'ERROR',
        ]));

        try {
            Logger::dropInstance();
            Logger::log('cache debug must be skipped', LOG_DEBUG, null, false, 'cache');
            Logger::log('cache error must be written', LOG_ERR, null, false, 'cache');
            Logger::log('other debug must be written', LOG_DEBUG);
            Logger::dropInstance();

            $files = glob($dir . '/*/*/*/*.log') ?: [];
            $this->assertNotEmpty($files, 'Log file not created');
            $content = implode('', array_map('file_get_contents', $files));
            $this->assertStringNotContainsString('cache debug must be skipped', $content);
            $this->assertStringContainsString('cache error must be written', $content);
            $this->assertStringContainsString('"channel":"cache"', $content);
            $this->assertStringContainsString('other debug must be written', $content);
        } finally {
            Logger::dropInstance();
            $configProperty->setValue($config, $defaultConfig);
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    public function testDisabledLevelDoesNotOpenTheLogFile(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $defaultConfig = $configProperty->getValue($config);
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-log-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $configProperty->setValue($config, array_merge($defaultConfig, [
            'log.output' => 'file',
            'default.log.path' => $dir,
            'log.level' => 'ERROR',
        ]));

        try {
            Logger::dropInstance();
            Logger::log('ignored', LOG_DEBUG);
            $this->assertSame([], glob($dir . '/*') ?: [], 'Suppressed logs must not touch the filesystem');
        } finally {
            Logger::dropInstance();
            $configProperty->setValue($config, $defaultConfig);
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    public function testLoggerOutputCanBeSelectedFromTheEnvironment(): void
    {
        $logger = (new \ReflectionClass(Logger::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Logger::class, 'resolveLogOutputTarget');

        try {
            putenv('PSFS_LOG_OUTPUT=stderr');
            [$target, $closeStreamOnDestruct] = $method->invoke($logger);
            $this->assertSame('php://stderr', $target);
            $this->assertFalse($closeStreamOnDestruct);

            putenv('PSFS_LOG_OUTPUT=STDOUT');
            [$target] = $method->invoke($logger);
            $this->assertSame('php://stdout', $target);
        } finally {
            putenv('PSFS_LOG_OUTPUT');
        }
    }

    public function testFormatterFollowsTheLogFormatSetting(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $defaultConfig = $configProperty->getValue($config);
        $logger = (new \ReflectionClass(Logger::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Logger::class, 'createFormatter');

        try {
            $configProperty->setValue($config, array_diff_key($defaultConfig, ['log.format' => true]));
            $this->assertInstanceOf(\Monolog\Formatter\LineFormatter::class, $method->invoke($logger));

            $configProperty->setValue($config, array_merge($defaultConfig, ['log.format' => 'json']));
            $this->assertInstanceOf(\Monolog\Formatter\JsonFormatter::class, $method->invoke($logger));

            $configProperty->setValue($config, array_merge($defaultConfig, ['log.format' => 'unknown']));
            $this->assertInstanceOf(\Monolog\Formatter\LineFormatter::class, $method->invoke($logger));

            putenv('PSFS_LOG_FORMAT=json');
            $this->assertInstanceOf(\Monolog\Formatter\JsonFormatter::class, $method->invoke($logger));
        } finally {
            putenv('PSFS_LOG_FORMAT');
            $configProperty->setValue($config, $defaultConfig);
        }
    }

    public function testJsonFormatWritesOneParsableObjectPerLine(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $defaultConfig = $configProperty->getValue($config);
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-log-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $configProperty->setValue($config, array_merge($defaultConfig, [
            'log.output' => 'file',
            'log.format' => 'json',
            'default.log.path' => $dir,
            'log.level' => 'DEBUG',
        ]));

        try {
            Logger::dropInstance();
            Logger::log('first json record', LOG_ERR, ['order_id' => 7], false, 'cache');
            Logger::log("second\njson record", LOG_WARNING);
            Logger::dropInstance();

            $files = glob($dir . '/*/*/*/*.log') ?: [];
            $this->assertNotEmpty($files, 'Log file not created');
            $lines = array_values(array_filter(explode("\n", implode('', array_map('file_get_contents', $files)))));
            $this->assertCount(2, $lines, 'A multi-line message must not break the one-record-per-line contract');

            $first = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('first json record', $first['message']);
            $this->assertSame('ERROR', $first['level_name']);
            $this->assertSame(7, $first['context']['order_id']);
            $this->assertSame('cache', $first['context']['channel']);
            $this->assertArrayHasKey('datetime', $first);
            $this->assertArrayHasKey('uid', $first['extra']);

            $second = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame("second\njson record", $second['message']);
            $this->assertSame('WARNING', $second['level_name']);
        } finally {
            Logger::dropInstance();
            $configProperty->setValue($config, $defaultConfig);
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
