<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;
use PSFS\controller\ConfigController;

/**
 * Class RouterTest
 * @package PSFS\tests
 */
class RouterTest extends TestCase
{

    /**
     * @return void
     */
    public function testRouterBasics()
    {
        $router = Router::getInstance();
        $config = Config::getInstance()->dumpConfig();
        $config['debug'] = true;
        Config::save($config, []);
        $this->assertNotNull($router);
        $this->assertInstanceOf(Router::class, $router);
        $this->assertTrue(Router::exists(Router::class), "Can't check the namespace");

        $slugs = $router->getSlugs();
        $this->assertNotEmpty($slugs);

        $routes = $router->getAllRoutes();
        $this->assertNotEmpty($routes);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testNotFound()
    {
        $this->expectExceptionCode(404);
        $this->expectException(\PSFS\base\exception\RouterException::class);
        Router::dropInstance();
        $router = Router::getInstance();
        $router->execute(uniqid(time(), true));
    }

    /**
     * @return void
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function testCanAccess()
    {
        $this->expectExceptionCode(401);
        $this->expectException(\PSFS\base\exception\UserAuthException::class);
        // Creates a default user
        Security::getInstance()->saveUser([
            'username' => uniqid('test', true),
            'password' => uniqid('test', true),
            'profile' => Security::ADMIN_ID_TOKEN,
        ]);
        $router = Router::getInstance();
        Admin::setTest(true);
        $router->execute('/admin/config');
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testPreconditions()
    {
        $this->expectExceptionCode(404);
        $this->expectException(\PSFS\base\exception\RouterException::class);
        Router::dropInstance();
        $router = Router::getInstance();
        SecurityHelper::setTest(true);
        Security::setTest(true);
        $config = Config::getInstance()->dumpConfig();
        $config['allow.double.slashes'] = false;
        Config::save($config);
        $router->execute('/admin//config');
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testPreconditionsNonStrict()
    {
        $this->expectException(\PSFS\base\exception\UserAuthException::class);
        Router::dropInstance();
        $router = Router::getInstance();
        SecurityHelper::setTest(true);
        Security::setTest(false);
        ConfigController::setTest(true);
        $config = Config::getInstance()->dumpConfig();
        $config['allow.double.slashes'] = true;
        Config::save($config);
        $router->execute('/admin//config');
    }

    /**
     * @return void
     */
    public function testGetRoute()
    {
        $router = Router::getInstance();
        $this->assertNotNull($router->getRoute(), "Can't gather the homepage route");
        $this->assertNotNull($router->getRoute('admin'), "Can't gather the admin route");
        $this->assertNotEquals($router->getRoute('admin'), $router->getRoute('admin', true), 'Absolute route is equal than normal');
        try {
            $router->getRoute(uniqid('test', true));
        } catch (\Exception $e) {
            $this->assertInstanceOf(RouterException::class, $e, 'Exception is not the expected: ' . get_class($e));
        }
    }

}
