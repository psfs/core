<?php
namespace PSFS\test;

use PSFS\base\Router;

/**
 * Class RouterTest
 * @package PSFS\test
 */
class RouterTest extends \PHPUnit_Framework_TestCase {

    public function testRouterBasics() {
        $router = Router::getInstance();
        $this->assertNotNull($router);
        $this->assertInstanceOf('\\PSFS\\base\\Router', $router);

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

        $router->execute(uniqid(time()));
    }
}