<?php

namespace PSFS\test;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\Dispatcher;

class PSFSTest extends TestCase {

    /**
     * Basic test for the basic funcitonality
     */
    public function testDispatcher()
    {
        /** @var \PSFS\Dispatcher $dispatcher */
        $dispatcher = Dispatcher::getInstance();

        // Is instance of Dispatcher?
        self::assertTrue($dispatcher instanceof Dispatcher);

        // Did timestamp generated?
        self::assertTrue($dispatcher->getTs() > 0);
    }

    /**
     * Basic test for Config functionality
     */
    public function testConfig()
    {
        $config = Config::getInstance();

        // Is config instance?
        self::assertTrue($config instanceof Config);

        // Is the platform configured?
        self::assertTrue(is_bool($config->isConfigured()));

        // Is the platform in debug mode?
        self::assertTrue(is_bool($config->getDebugMode()));

        // Check the variable extraction
        self::assertEmpty($config->get(uniqid()));
    }

    /**
     * Basic test for Router functionality
     */
    public function testRouter()
    {
        $router = Router::getInstance();

        // Is ROuter instance?
        self::assertTrue($router instanceof Router);

        // Check if route file exists
        self::assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json");

        // CHecks if we have admin routes as minimal routes
        $adminRoutes = AdminHelper::getAdminRoutes($router->getRoutes());
        self::assertNotEmpty($adminRoutes);
        self::assertArrayHasKey("PSFS", $adminRoutes);
    }

    /**
     * Basic test for Security functionality
     */
    public function testSecurity()
    {
        $security = Security::getInstance();

        // Is Security instance?
        self::assertTrue($security instanceof Security);
    }

    /**
     * Basic test for Request functionality
     */
    public function testRequest()
    {
        $request = Request::getInstance();

        // Is Request instance?
        self::assertTrue($request instanceof Request);

        // Check headers, uploads and cookies checkers
        self::assertTrue(is_bool($request->hasHeader("session")));
        self::assertTrue(is_bool($request->hasUpload()));
        self::assertTrue(is_bool($request->hasCookies()));
        self::assertTrue(is_bool($request->isAjax()));

        // Checks if timestamp was generated
        self::assertNotNull($request->getTs());
    }

}
