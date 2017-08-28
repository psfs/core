<?php
namespace PSFS\test;

use PSFS\base\config\Config;
use PSFS\base\Router;

/**
 * Class RouterTest
 * @package PSFS\test
 */
class RouterTest extends \PHPUnit_Framework_TestCase {

    public function testRouterBasics() {
        $router = Router::getInstance();
        $config = Config::getInstance()->dumpConfig();
        $config['debug'] = true;
        Config::save($config, []);
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