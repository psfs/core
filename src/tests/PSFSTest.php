<?php

namespace PSFS\tests;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\Dispatcher;

class PSFSTest extends TestCase {

    /**
     * Basic test for the basic functionality
     * @covers \PSFS\Dispatcher
     */
    public function testDispatcher()
    {
        /** @var \PSFS\Dispatcher $dispatcher */
        $dispatcher = Dispatcher::getInstance();

        // Is instance of Dispatcher?
        $this->assertTrue($dispatcher instanceof Dispatcher);

        // Did timestamp generated?
        $this->assertTrue($dispatcher->getTs() > 0);
    }

    /**
     * Basic test for Config functionality
     * @covers \PSFS\base\config\Config
     */
    public function testConfig()
    {
        $config = Config::getInstance();

        // Is config instance?
        $this->assertTrue($config instanceof Config);

        // Is the platform configured?
        $this->assertTrue(is_bool($config->isConfigured()));

        // Is the platform in debug mode?
        $this->assertTrue(is_bool($config->getDebugMode()));

        // Check the variable extraction
        $this->assertEmpty($config->get(uniqid()));
    }

    /**
     * Basic test for Router functionality
     * @covers \PSFS\base\Router
     */
    public function testRouter()
    {
        $router = Router::getInstance();

        // Is ROuter instance?
        $this->assertTrue($router instanceof Router);

        // Check if route file exists
        $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json");

        // CHecks if we have admin routes as minimal routes
        $adminRoutes = AdminHelper::getAdminRoutes($router->getRoutes());
        $this->assertNotEmpty($adminRoutes);
        $this->assertArrayHasKey("PSFS", $adminRoutes);
    }

    /**
     * Basic test for Security functionality
     * @covers \PSFS\base\Security
     */
    public function testSecurity()
    {
        $security = Security::getInstance();

        // Is Security instance?
        $this->assertTrue($security instanceof Security);
    }

    /**
     * Basic test for Request functionality
     * @covers \PSFS\base\Request
     */
    public function testRequest()
    {
        $request = Request::getInstance();

        // Is Request instance?
        $this->assertTrue($request instanceof Request);

        // Check headers, uploads and cookies checkers
        $this->assertTrue(is_bool($request->hasHeader("session")));
        $this->assertTrue(is_bool($request->hasUpload()));
        $this->assertTrue(is_bool($request->hasCookies()));
        $this->assertTrue(is_bool($request->isAjax()));

        // Checks if timestamp was generated
        $this->assertNotNull($request->getTs());
    }

}
