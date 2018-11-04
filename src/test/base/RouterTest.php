<?php
namespace PSFS\test;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\ResponseHelper;
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
        $router = Router::getInstance();
        $router->execute(uniqid(time(), true));
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

    public function testExecuteRoute() {
        $router = Router::getInstance();
        Admin::setTest(true);
        Security::setTest(true);
        ResponseHelper::setTest(true);
        $result = $router->execute('/admin/config/params');
        $this->assertNotEmpty($result, 'Empty response');
        $jsonDecodedResponse = json_decode($result, true);
        $this->assertNotNull($jsonDecodedResponse, 'Bad JSON response');
        $this->assertTrue(is_array($jsonDecodedResponse), 'Bad decoded response');
        Admin::setTest(false);
        Security::setTest(false);
        ResponseHelper::setTest(false);
    }

}