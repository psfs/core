<?php
namespace PSFS\test\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;
use PSFS\Dispatcher;

/**
 * Class DispatcherTest
 * @package PSFS\test\base
 */
class DispatcherTest extends TestCase
{

    /**
     * Método que devuelve una instancia del Dspatcher
     * @param PHPUnit_Framework_MockObject_MockObject $config
     * @param PHPUnit_Framework_MockObject_MockObject $router
     * @param PHPUnit_Framework_MockObject_MockObject $security
     * @return Dispatcher
     */
    private function getInstance($config = null, $router = null, $security = null)
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
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function mockConfiguredDebugConfig($configured = true, $debug = true)
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->any())->method('isConfigured')->will($this->returnValue($configured));
        $config->expects($this->any())->method('getDebugMode')->will($this->returnValue($debug));
        return $config;
    }

    /**
     * Método que mockea la clase Router
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function mockDebugRouter()
    {
        return $this->createMock(Router::class);
    }

    /**
     * Método que mockea la clase Router
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function mockDebugSecurity()
    {
        return $this->createMock(Security::class);
    }

    public function testConstructor()
    {
        $dispatcher = $this->getInstance();

        self::assertNotNull($dispatcher);
        self::assertInstanceOf(Dispatcher::class, $dispatcher);

    }

    public function testMem()
    {
        $dispatcher = $this->getInstance();
        self::assertNotNull($dispatcher->getMem('Bytes'));
        self::assertNotNull($dispatcher->getMem('KBytes'));
        self::assertNotNull($dispatcher->getMem('MBytes'));
        self::assertNotNull($dispatcher->getMem());
    }

    public function testTS()
    {
        $dispatcher = $this->getInstance();
        $ts = $dispatcher->getTs();
        self::assertNotNull($ts);
        usleep(200);
        self::assertGreaterThan($ts, $dispatcher->getTs());
    }

    /**
     * Check if notice was converted to exception
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
            self::assertTrue(true);
        }
    }

    /**
     * Check if warning was converted to exception
     */
    public function testWarning()
    {
        $this->getInstance($this->mockConfiguredDebugConfig());
        try {
            //This throws a warning because file 'test.txt' not exists
            file_get_contents(__DIR__ . 'test.txt');
            $this->fail('Exception has not been thrown');
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
    }

    public function testNormalExecution()
    {
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willReturn('OK');

        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
        $response = $dispatcher->run();
        self::assertNotNull($response);
        self::assertEquals('OK', $response);
    }

    public function testNotConfigured()
    {
        $this->expectExceptionMessage("CONFIG");
        $this->expectException(\PSFS\base\exception\ConfigException::class);
        $config = $this->mockConfiguredDebugConfig(false);
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \PSFS\base\exception\ConfigException('CONFIG'));
        $dispatcher = $this->getInstance($config, $router);
        $dispatcher->run();
    }

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

        $dispatcher->run();
    }

    public function testNotFound()
    {
        $this->expectExceptionMessage("NOT FOUND");
        $this->expectException(\PSFS\base\exception\RouterException::class);
        $config = $this->mockConfiguredDebugConfig();
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willThrowException(new \PSFS\base\exception\RouterException('NOT FOUND'));
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \PSFS\base\exception\RouterException('NOT FOUND'));
        $dispatcher = $this->getInstance($config, $router);

        $dispatcher->run();
    }

    public function testCatchException()
    {
        $this->expectExceptionMessage("CATCH EXCEPTION");
        $this->expectException(\Exception::class);
        $router = $this->mockDebugRouter();
        $router->expects($this->any())->method('execute')->willThrowException(new \Exception('CATCH EXCEPTION'));
        $router->expects($this->any())->method('httpNotFound')->willThrowException(new \Exception('CATCH EXCEPTION'));
        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
        $dispatcher->run();
    }

    public function testStats() {
        Inspector::stats('test1', Inspector::SCOPE_DEBUG);
        $stats = Inspector::getStats();
        self::assertNotEmpty($stats, 'Empty stats');
        Inspector::stats('test2', Inspector::SCOPE_DEBUG);
        $secondStats = Inspector::getStats(Inspector::SCOPE_DEBUG);
        self::assertNotEmpty($secondStats, 'Empty stats');
        self::assertNotEquals($stats, $secondStats, 'Stats are similar');
    }

    public function testExecuteRoute() {
        $router = Router::getInstance();
        $security = Security::getInstance();
        $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router, $security);
        Admin::setTest(true);
        ResponseHelper::setTest(true);
        Security::setTest(true);
        SecurityHelper::setTest(true);
        Config::setTest(true);
        $result = $dispatcher->run('/admin/config/params');
        self::assertNotEmpty($result, 'Empty response');
        $jsonDecodedResponse = json_decode($result, true);
        self::assertNotNull($jsonDecodedResponse, 'Bad JSON response');
        self::assertTrue(is_array($jsonDecodedResponse), 'Bad decoded response');
        Admin::setTest(false);
        Security::setTest(false);
        SecurityHelper::setTest(false);
        ResponseHelper::setTest(false);
        Config::setTest(false);
    }
}
