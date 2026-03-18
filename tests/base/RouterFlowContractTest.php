<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\interfaces\PreConditionedRunInterface;

class RouterFlowContractTest extends TestCase
{
    private array $serverBackup = [];
    private array $cookieBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];
    private array $filesBackup = [];
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->cookieBackup = $_COOKIE;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;
        $this->filesBackup = $_FILES;
        $this->configBackup = Config::getInstance()->dumpConfig();
        Security::dropInstance();
        Request::dropInstance();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_COOKIE = $this->cookieBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        $_FILES = $this->filesBackup;
        Request::dropInstance();
        Security::dropInstance();
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        RouterFlowController::reset();
        RouterFlowPreconditionController::reset();
    }

    public function testMatchPrecedencePrefersExactMethodOverAll(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'ALL#|#/contract/match' => $this->buildAction('all', RouterFlowController::class, 'ALL'),
            'GET#|#/contract/match' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);

        $this->bootstrapRequest('/contract/match', 'GET');
        $result = $router->execute('/contract/match');

        $this->assertSame('get', $result);
        $this->assertSame(['get'], RouterFlowController::$calls);
    }

    public function testMatchUsesAllFallbackWhenExactMethodDoesNotExist(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'ALL#|#/contract/all-fallback' => $this->buildAction('all', RouterFlowController::class, 'ALL'),
        ]);

        $this->bootstrapRequest('/contract/all-fallback', 'PATCH');
        $result = $router->execute('/contract/all-fallback');

        $this->assertSame('all', $result);
        $this->assertSame(['all'], RouterFlowController::$calls);
    }

    public function testMatchPrecedencePrefersMostSpecificPatternWithinSameMethod(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/config/{key}' => $this->buildAction('all', RouterFlowController::class, 'GET'),
            'GET#|#/contract/config/params' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);

        $this->bootstrapRequest('/contract/config/params', 'GET');
        $result = $router->execute('/contract/config/params');

        $this->assertSame('get', $result);
        $this->assertSame(['get'], RouterFlowController::$calls);
    }

    public function testRequirementsValidationContract(): void
    {
        $router = new TestableRouter();
        $method = new \ReflectionMethod(Router::class, 'checkRequirements');
        $method->setAccessible(true);

        $action = [
            'requirements' => ['id', 'type'],
        ];

        $this->assertFalse((bool)$method->invoke($router, $action, ['id' => '10']));
        $this->assertTrue((bool)$method->invoke($router, $action, ['id' => '10', 'type' => 'api']));
        $this->assertTrue((bool)$method->invoke($router, ['requirements' => []], []));
    }

    public function testPreconditionsRunBeforeAction(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/pre' => $this->buildAction('run', RouterFlowPreconditionController::class, 'GET'),
        ]);
        $this->bootstrapRequest('/contract/pre', 'GET');

        $result = $router->execute('/contract/pre');
        $this->assertSame('ok', $result);
        $this->assertSame(['__check', 'preRun', 'run'], RouterFlowPreconditionController::$calls);
    }

    public function testCacheRouteExecutionContractKeepsInvocationSemantics(): void
    {
        $config = $this->configBackup;
        $config['debug'] = false;
        $config['cache.data.enable'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/cache/{id}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 120, ['id']),
        ]);

        $this->bootstrapRequest('/contract/cache/42', 'GET', ['expand' => '1']);
        $result = $router->execute('/contract/cache/42');
        $this->assertSame('cached:42', $result);

        $sessionAction = Security::getInstance()->getSessionKey(Cache::CACHE_SESSION_VAR);
        $this->assertIsArray($sessionAction);
        $this->assertSame(120, $sessionAction['cache']);
        $this->assertArrayHasKey('id', $sessionAction['params']);
        $this->assertSame('42', $sessionAction['params']['id']);
    }

    public function testNotFoundMappingForUnknownRoute(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([]);
        $this->bootstrapRequest('/contract/missing', 'GET');

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Page not found');
        $router->execute('/contract/missing');
    }

    public function testNotFoundMappingPreservesCodeWhenActionThrowsRouterException(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/boom' => $this->buildAction('boom', RouterFlowController::class, 'GET'),
        ]);
        $this->bootstrapRequest('/contract/boom', 'GET');

        try {
            $router->execute('/contract/boom');
            $this->fail('Expected RouterException was not thrown');
        } catch (RouterException $exception) {
            $this->assertSame(418, $exception->getCode());
            $this->assertSame('Page not found', $exception->getMessage());
        }
    }

    public function testRequirementsFailureMapsToNotFoundContract(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/require/{id}/{type}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 0, ['id', 'type']),
        ]);
        $this->bootstrapRequest('/contract/require/42', 'GET');

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Page not found');
        $router->execute('/contract/require/42');
    }

    public function testHydrateRoutingIgnoresNullOrEmptyHomeAction(): void
    {
        $router = new HomeHydrationRouter();
        $router->setGeneratedRoutes([
            'GET#|#/admin' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);

        $config = $this->configBackup;
        $config['home.action'] = '';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $router->hydrateRouting();
        $this->assertArrayNotHasKey('/', $router->getRoutes());

        $config['home.action'] = 'admin';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $router->hydrateRouting();
        $this->assertArrayHasKey('/', $router->getRoutes());
    }

    private function bootstrapRequest(string $uri = '/', string $method = 'GET', array $query = []): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_USER_AGENT' => 'phpunit-router-contract',
        ];
        $_COOKIE = [];
        $_GET = $query;
        $_REQUEST = $query;
        $_FILES = [];
        Request::dropInstance();
        Request::getInstance()->init();
    }

    private function buildAction(string $method, string $class, string $http = 'GET', int $cache = 0, array $requirements = []): array
    {
        return [
            'method' => $method,
            'params' => [],
            'default' => null,
            'pattern' => '',
            'slug' => '/contract/test',
            'label' => 'contract',
            'icon' => '',
            'module' => 'PSFS',
            'visible' => true,
            'http' => $http,
            'cache' => $cache,
            'requirements' => $requirements,
            'class' => $class,
        ];
    }
}

class TestableRouter extends Router
{
    public function __construct()
    {
    }

    public function seedRoutes(array $routing, array $slugs = []): void
    {
        $this->setRouterProperty('routing', $routing);
        $this->setRouterProperty('slugs', $slugs);
        $this->setRouterProperty('domains', []);
        $this->setRouterProperty('cache', Cache::getInstance());
        $this->setLoaded(true);
    }

    protected function setRouterProperty(string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this, $value);
    }
}

class HomeHydrationRouter extends TestableRouter
{
    private array $generatedRoutes = [];

    public function setGeneratedRoutes(array $routes): void
    {
        $this->generatedRoutes = $routes;
    }

    protected function generateRouting()
    {
        $this->setRouterProperty('routing', $this->generatedRoutes);
    }
}

class RouterFlowController
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function all(): string
    {
        self::$calls[] = 'all';
        return 'all';
    }

    public function get(): string
    {
        self::$calls[] = 'get';
        return 'get';
    }

    public function cached(string $id): string
    {
        self::$calls[] = 'cached';
        return 'cached:' . $id;
    }

    public function boom(): string
    {
        throw new RouterException('boom', 418);
    }
}

class RouterFlowPreconditionController implements PreConditionedRunInterface
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function __check()
    {
        self::$calls[] = '__check';
        return true;
    }

    public function preRun()
    {
        self::$calls[] = 'preRun';
        return true;
    }

    public function run(): string
    {
        self::$calls[] = 'run';
        return 'ok';
    }
}
