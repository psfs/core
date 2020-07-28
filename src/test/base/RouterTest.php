<?php
namespace PSFS\test\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;
use PSFS\controller\ConfigController;
use PSFS\Dispatcher;

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
        self::assertNotNull($router);
        self::assertInstanceOf(Router::class, $router);
        self::assertTrue(Router::exists(Router::class), "Can't check the namespace");

        $slugs = $router->getSlugs();
        self::assertNotEmpty($slugs);

        $routes = $router->getAllRoutes();
        self::assertNotEmpty($routes);
    }

    public function testNotFound() {
        $this->expectExceptionCode(404);
        $this->expectException(\PSFS\base\exception\RouterException::class);
        Router::dropInstance();
        $router = Router::getInstance();
        $router->execute(uniqid(time(), true));
    }

    public function testCanAccess() {
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

    public function testPreconditions() {
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

    public function testPreconditionsNonStrict() {
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

    public function testGetRoute() {
        $router = Router::getInstance();
        self::assertNotNull($router->getRoute(), "Can't gather the homepage route");
        self::assertNotNull($router->getRoute('admin'), "Can't gather the admin route");
        self::assertNotEquals($router->getRoute('admin'), $router->getRoute('admin', true), 'Absolute route is equal than normal');
        try {
            $router->getRoute(uniqid('test', true));
        } catch(\Exception $e) {
            self::assertInstanceOf(RouterException::class, $e, 'Exception is not the expected: ' . get_class($e));
        }
    }

}
