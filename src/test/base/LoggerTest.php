<?php
namespace PSFS\test\base;
use PSFS\base\Logger;

/**
 * Class DispatcherTest
 * @package PSFS\test\base
 */
class LoggerTest extends \PHPUnit_Framework_TestCase
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
        $instance2 = $this->getInstance();

        $this->assertEquals($instance1, $instance2, 'Singleton instances are not equals');
    }
}