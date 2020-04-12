<?php
namespace PSFS\test;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;

/**
 * Class RouterTest
 * @package PSFS\test
 */
class RouterTest extends TestCase {

    public function testRouterBasics() {
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
     * @expectedException \PSFS\base\exception\RouterException
     * @expectedExceptionCode 404
     */
    public function testNotFound() {
        Router::dropInstance();
        $router = Router::getInstance();
        $router->execute(uniqid(time(), true));
    }

    /**
     * @expectedException \PSFS\base\exception\UserAuthException
     * @expectedExceptionCode 401
     */
    public function testCanAccess() {
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
     * @expectedException \PSFS\base\exception\RouterException
     * @expectedExceptionCode 412
     */
    public function testPreconditions() {
        $router = Router::getInstance();
        SecurityHelper::setTest(true);
        Security::setTest(true);
        $config = Config::getInstance()->dumpConfig();
        $config['allow.double.slashes'] = false;
        Config::save($config);
        $router->execute('/admin//swagger-ui');
    }

    /**
     * @expectedException \PSFS\base\exception\RouterException
     * @expectedExceptionCode 404
     */
    public function testPreconditionsNonStrict() {
        $router = Router::getInstance();
        SecurityHelper::setTest(true);
        Security::setTest(true);
        $config = Config::getInstance()->dumpConfig();
        $config['allow.double.slashes'] = true;
        Config::save($config);
        $router->execute('/admin//swagger-ui');
    }

    public function testGetRoute() {
        $router = Router::getInstance();
        $this->assertNotNull($router->getRoute(), "Can't gather the homepage route");
        $this->assertNotNull($router->getRoute('admin'), "Can't gather the admin route");
        $this->assertNotEquals($router->getRoute('admin'), $router->getRoute('admin', true), 'Absolute route is equal than normal');
        try {
            $router->getRoute(uniqid('test', true));
        } catch(\Exception $e) {
            $this->assertInstanceOf(RouterException::class, $e, 'Exception is not the expected: ' . get_class($e));
        }
    }

}
