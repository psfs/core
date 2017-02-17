<?php
    namespace PSFS\test\base;

    use PSFS\Dispatcher;

    /**
     * Class DispatcherTest
     * @package PSFS\test\base
     */
    class DispatcherTest extends \PHPUnit_Framework_TestCase {

        /**
         * Método que devuelve una instancia del Dspatcher
         * @param PHPUnit_Framework_MockObject_MockObject $config
         * @param PHPUnit_Framework_MockObject_MockObject $router
         * @param PHPUnit_Framework_MockObject_MockObject $security
         * @return Dispatcher
         */
        private function getInstance($config = null, $router = null, $security = null) {
            $dispatcher = Dispatcher::getInstance();
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
        private function mockConfiguredDebugConfig($configured = true, $debug = true) {
            $config = $this->getMock("\\PSFS\\base\\config\\Config");
            $config->expects($this->any())->method("isConfigured")->will($this->returnValue($configured));
            $config->expects($this->any())->method("getDebugMode")->will($this->returnValue($debug));
            return $config;
        }

        /**
         * Método que mockea la clase Router
         * @return \PHPUnit_Framework_MockObject_MockObject
         */
        private function mockDebugRouter() {
            return $this->getMock("\\PSFS\\base\\Router");
        }

        /**
         * Método que mockea la clase Router
         * @return \PHPUnit_Framework_MockObject_MockObject
         */
        private function mockDebugSecurity() {
            return $this->getMock("\\PSFS\\base\\Security");
        }

        public function testConstructor() {
            $dispatcher = $this->getInstance();

            $this->assertNotNull($dispatcher);
            $this->assertTrue($dispatcher instanceof Dispatcher);

        }

        public function testMem() {
            $dispatcher = $this->getInstance();
            $this->assertNotNull($dispatcher->getMem("Bytes"));
            $this->assertNotNull($dispatcher->getMem("KBytes"));
            $this->assertNotNull($dispatcher->getMem("MBytes"));
            $this->assertNotNull($dispatcher->getMem());
        }

        public function testTS() {
            $dispatcher = $this->getInstance();
            $ts = $dispatcher->getTs();
            $this->assertNotNull($ts);
            usleep(200);
            $this->assertGreaterThan($ts, $dispatcher->getTs());
        }

        /**
         * Check if notice was converted to exception
         */
        public function testNotice() {
            $this->getInstance($this->mockConfiguredDebugConfig());
            try {
                $test = array();
                //This throws notice because position 0 in array not exists
                $test = $test[0] === true;
                unset($test);
                $this->fail('Exception has not been thrown');
            } catch(\Exception $e) {
                $this->assertTrue(true);
            }
        }

        /**
         * Check if warning was converted to exception
         */
        public function testWarning() {
            $this->getInstance($this->mockConfiguredDebugConfig());
            try {
                //This throws a warning because file 'test.txt' not exists
                file_get_contents(__DIR__ . "test.txt");
                $this->fail('Exception has not been thrown');
            } catch(\Exception $e) {
                $this->assertTrue(true);
            }
        }

        public function testNormalExecution() {
            $router = $this->mockDebugRouter();
            $router->expects($this->any())->method("execute")->willReturn("OK");

            $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
            $response = $dispatcher->run();
            $this->assertNotNull($response);
            $this->assertEquals("OK", $response);
        }

        /**
         * @expectedException \PSFS\base\exception\ConfigException
         * @expectedExceptionMessage CONFIG
         */
        public function testNotConfigured() {
            $config = $this->mockConfiguredDebugConfig(false);
            $router = $this->mockDebugRouter();
            $router->expects($this->any())->method("httpNotFound")->willThrowException(new \PSFS\base\exception\ConfigException("CONFIG"));
            $router->expects($this->any())->method("getAdmin")->willThrowException(new \PSFS\base\exception\ConfigException("CONFIG"));
            $dispatcher = $this->getInstance($config, $router);

            $dispatcher->run();
        }

        /**
         * @expectedException \PSFS\base\exception\SecurityException
         * @expectedExceptionMessage NOT AUTHORIZED
         */
        public function testNotAuthorized() {
            $config = $this->mockConfiguredDebugConfig();
            $router = $this->mockDebugRouter();
            $router->expects($this->any())->method("execute")->willThrowException(new \PSFS\base\exception\SecurityException("NOT AUTHORIZED"));
            $security = $this->mockDebugSecurity();
            $security->expects($this->any())->method("notAuthorized")->willThrowException(new \PSFS\base\exception\SecurityException("NOT AUTHORIZED"));
            $dispatcher = $this->getInstance($config, $router, $security);

            $dispatcher->run();
        }

        /**
         * @expectedException \PSFS\base\exception\RouterException
         * @expectedExceptionMessage NOT FOUND
         */
        public function testNotFound() {
            $config = $this->mockConfiguredDebugConfig();
            $router = $this->mockDebugRouter();
            $router->expects($this->any())->method("execute")->willThrowException(new \PSFS\base\exception\RouterException("NOT FOUND"));
            $router->expects($this->any())->method("httpNotFound")->willThrowException(new \PSFS\base\exception\RouterException("NOT FOUND"));
            $dispatcher = $this->getInstance($config, $router);

            $dispatcher->run();
        }

        /**
         * @expectedException \Exception
         * @expectedExceptionMessage CATCH EXCEPTION
         */
        public function testCatchException() {
            $router = $this->mockDebugRouter();
            $router->expects($this->any())->method("execute")->willThrowException(new \Exception("CATCH EXCEPTION"));
            $router->expects($this->any())->method("httpNotFound")->willThrowException(new \Exception("CATCH EXCEPTION"));
            $dispatcher = $this->getInstance($this->mockConfiguredDebugConfig(), $router);
            $dispatcher->run();
        }
    }