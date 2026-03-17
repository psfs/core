<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\controller\base\Admin;
use PSFS\controller\ConfigController;

/**
 * Class RouterTest
 * @package PSFS\tests
 */
class RouterTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        Router::dropInstance();
        parent::tearDown();
    }

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
            'profile' => AuthHelper::ADMIN_ID_TOKEN,
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

    public function testRunHandlesFalseResultAndExceptions(): void
    {
        Router::run(new RouterRunHelper(), 'returnsFalse', false);
        $this->assertTrue(true);

        Router::run(new RouterRunHelper(), 'throwsException', false);
        $this->assertTrue(true);
    }

    public function testRunCanRethrowAndHttpNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        Router::run(new RouterRunHelper(), 'throwsException', true);
    }

    public function testDebugLoadSkipRouteGenerationAndHttpNotFoundWrapper(): void
    {
        $config = Config::getInstance()->dumpConfig();
        $config['skip.route_generation'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $router = Router::getInstance();

        $method = new \ReflectionMethod($router, 'debugLoad');
        $method->setAccessible(true);
        $method->invoke($router);

        ResponseHelper::setTest(true);
        $response = $router->httpNotFound(new \Exception('Not found', 404), true);
        $this->assertSame(404, $response);
        ResponseHelper::setTest(false);
    }

}

class RouterRunHelper
{
    public function returnsFalse(): bool
    {
        return false;
    }

    public function throwsException(): bool
    {
        throw new \RuntimeException('run-exception');
    }
}
