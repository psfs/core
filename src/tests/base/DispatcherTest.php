<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;
use PSFS\Dispatcher;

/**
 * Class DispatcherTest
 * @package PSFS\tests\base
 */
class DispatcherTest extends TestCase
{

    /**
     * Método que devuelve una instancia del Dspatcher
     * @param MockObject|Config|null $config
     * @param MockObject|Router|null $router
     * @param MockObject|Security|null $security
     * @return Dispatcher
     */
    private function getInstance(MockObject|Config $config = null, MockObject|Router $router = null, MockObject|Security $security = null): Dispatcher
    {
        $dispatcher = Dispatcher::getInstance();
        Security::setTest(false);
        if (null !== $config) {
            $dispatcher->config = $config;
        }
        if (null !== $router) {
            $dispatcher->router = $router;
        }
        if (null !== $security) {
            $dispatcher->security = $security;
        }
        return $dispatcher;
    }

    /**
     * Método que crea un objeto Mock seteado a debug
     * @param boolean $configured
     * @param boolean $debug
     * @return MockObject
     * @throws Exception
     */
    private function mockConfiguredDebugConfig(bool $configured = true, bool $debug = true): MockObject
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->any())->method('isConfigured')->will($this->returnValue($configured));
        $config->expects($this->any())->method('getDebugMode')->will($this->returnValue($debug));
        return $config;
    }

    /**
     * Método que mockea la clase Router
     * @return MockObject
     * @throws Exception
     */
    private function mockDebugRouter(): MockObject
    {
        return $this->createMock(Router::class);
    }

    /**
     * Método que mockea la clase Router
     * @return MockObject
     * @throws Exception
     */
    private function mockDebugSecurity(): MockObject
    {
        return $this->createMock(Security::class);
    }

    /**
     * @covers \PSFS\Dispatcher
     * @return void
     */
    public function testConstructor()
    {
        $dispatcher = $this->getInstance();

        $this->assertNotNull($dispatcher);
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

    }

    /**
     * @covers \PSFS\Dispatcher
     * @return void
     */
    public function testMem()
    {
        $dispatcher = $this->getInstance();
        $this->assertNotNull($dispatcher->getMem('Bytes'));
        $this->assertNotNull($dispatcher->getMem('KBytes'));
        $this->assertNotNull($dispatcher->getMem('MBytes'));
        $this->assertNotNull($dispatcher->getMem());
    }

    /**
     * @covers \PSFS\Dispatcher
     * @return void
     */
    public function testTS()
    {
        $dispatcher = $this->getInstance();
        $ts = $dispatcher->getTs();
        $this->assertNotNull($ts);
        usleep(200);
        $this->assertGreaterThan($ts, $dispatcher->getTs());
    }

    /**
     * Check if notice was converted to exception
     * @covers \PSFS\Dispatcher
     * @throws Exception
     */
    public function testNotice()
    {
        $this->getInstance($this->mockConfiguredDebugConfig());
        try {
            $test = array();
            //This throws notice because position 0 in array not exists
            $test = $test[0] === true;
            unset($test);
            $this->fail('Exception has not been thrown');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Check if warning was converted to exception
     * @covers \PSFS\Dispatcher
     * @throws Exception
     */
    public function testWarning()
    {
        $this->getInstance($this->mockConfiguredDebugConfig());
        try {
            //This throws a warning because file 'test.txt' not exists
            file_get_contents(__DIR__ . 'test.txt');
            $this->fail('Exception has not been thrown');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @covers \PSFS\base\Security
     * @covers \PSFS\Dispatcher
     * @throws GeneratorException
     * @throws Exception
     */
    public function testNormalExecution()
    {
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willReturn('OK');

        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
        $response = $dispatcher->run();
        $this->assertNotNull($response);
        $this->assertEquals('OK', $response);
    }

    /**
     * @covers \PSFS\base\Security
     * @throws GeneratorException
     * @throws Exception
     */
    public function testNotConfigured()
    {
        $this->expectExceptionMessage("CONFIG");
        $this->expectException(\PSFS\base\exception\ConfigException::class);
        $config = $this->mockConfiguredDebugConfig(false);
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \PSFS\base\exception\ConfigException('CONFIG'));
        $dispatcher = $this->getInstance($config, $router);
        Security::setTest(true);
        $dispatcher->run();
    }

    /**
     * @covers \PSFS\base\Security
     * @throws GeneratorException
     * @throws Exception
     */
    public function testNotAuthorized()
    {
        $this->expectExceptionMessage("NOT AUTHORIZED");
        $this->expectException(\PSFS\base\exception\SecurityException::class);
        $config = $this->mockConfiguredDebugConfig();
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willThrowException(new \PSFS\base\exception\SecurityException('NOT AUTHORIZED'));
        Security::dropInstance();
        $security = $this->mockDebugSecurity();
        $security->expects($this->any())->method('notAuthorized')->willThrowException(new \PSFS\base\exception\SecurityException('NOT AUTHORIZED'));
        $dispatcher = $this->getInstance($config, $router, $security);
        Security::setTest(true);
        $dispatcher->run();
    }

    /**
     * @covers \PSFS\base\Security
     * @throws GeneratorException
     * @throws Exception
     */
    public function testNotFound()
    {
        $this->expectExceptionMessage("NOT FOUND");
        $this->expectException(\PSFS\base\exception\RouterException::class);
        $config = $this->mockConfiguredDebugConfig();
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willThrowException(new \PSFS\base\exception\RouterException('NOT FOUND'));
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \PSFS\base\exception\RouterException('NOT FOUND'));
        $dispatcher = $this->getInstance($config, $router);
        Security::setTest(true);
        $dispatcher->run();
    }

    /**
     * @covers \PSFS\base\Security
     * @throws GeneratorException
     * @throws Exception
     */
    public function testCatchException()
    {
        $this->expectExceptionMessage("CATCH EXCEPTION");
        $this->expectException(\Exception::class);
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willThrowException(new \Exception('CATCH EXCEPTION'));
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \Exception('CATCH EXCEPTION'));
        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
        Security::setTest(true);
        $dispatcher->run();
    }

    /**
     * @covers \PSFS\base\types\helpers\Inspector
     * @return void
     */
    public function testStats()
    {
        Inspector::stats('test1', Inspector::SCOPE_DEBUG);
        $stats = Inspector::getStats();
        $this->assertNotEmpty($stats, 'Empty stats');
        Inspector::stats('test2', Inspector::SCOPE_DEBUG);
        $secondStats = Inspector::getStats(Inspector::SCOPE_DEBUG);
        $this->assertNotEmpty($secondStats, 'Empty stats');
        $this->assertNotEquals($stats, $secondStats, 'Stats are similar');
    }

    /**
     * @covers \PSFS\controller\base\Admin
     * @covers \PSFS\base\types\helpers\ResponseHelper
     * @covers \PSFS\base\types\helpers\SecurityHelper
     * @covers \PSFS\base\Security
     * @throws GeneratorException
     * @throws Exception
     */
    public function testExecuteRoute()
    {
        $router = Router::getInstance();
        $security = Security::getInstance();
        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router, $security);
        Admin::setTest(true);
        ResponseHelper::setTest(true);
        Security::setTest(true);
        SecurityHelper::setTest(true);
        Config::setTest(true);
        $result = $dispatcher->run('/admin/config/params');
        $this->assertNotEmpty($result, 'Empty response');
        $jsonDecodedResponse = json_decode($result, true);
        $this->assertNotNull($jsonDecodedResponse, 'Bad JSON response');
        $this->assertTrue(is_array($jsonDecodedResponse), 'Bad decoded response');
        Admin::setTest(false);
        Security::setTest(false);
        SecurityHelper::setTest(false);
        ResponseHelper::setTest(false);
        Config::setTest(false);
    }
}
