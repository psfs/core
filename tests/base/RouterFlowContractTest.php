<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
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
        StageTrackingRouter::resetTrace();
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
        StageTrackingRouter::resetTrace();
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

    public function testRouterExecutionUsesStagedPipelineOrder(): void
    {
        $router = new StageTrackingRouter();
        $router->seedRoutes([
            'GET#|#/contract/stages' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);

        $this->bootstrapRequest('/contract/stages', 'GET');
        $result = $router->execute('/contract/stages');

        $this->assertSame('get', $result);
        $this->assertSame(['load', 'match', 'execute'], StageTrackingRouter::getTrace());
    }

    public function testRouterStagePipelineStopsBeforeExecuteWhenRouteIsMissing(): void
    {
        $router = new StageTrackingRouter();
        $router->seedRoutes([]);

        $this->bootstrapRequest('/contract/stages/missing', 'GET');
        try {
            $router->execute('/contract/stages/missing');
            $this->fail('Expected RouterException was not thrown');
        } catch (RouterException $exception) {
            $this->assertSame('Page not found', $exception->getMessage());
        }

        $this->assertSame(['load', 'match', 'map'], StageTrackingRouter::getTrace());
    }

    public function testRouterStagePipelineIncludesExecuteOnPreconditionsFailure(): void
    {
        $router = new StageTrackingRouter();
        $router->seedRoutes([
            'GET#|#/contract/stages/require/{id}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 0, ['id', 'type']),
        ]);

        $this->bootstrapRequest('/contract/stages/require/42', 'GET');
        try {
            $router->execute('/contract/stages/require/42');
            $this->fail('Expected RouterException was not thrown');
        } catch (RouterException $exception) {
            $this->assertSame('Page not found', $exception->getMessage());
        }

        $this->assertSame(['load', 'match', 'execute', 'map'], StageTrackingRouter::getTrace());
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

    public function testAdminManagerCanonicalItemRouteWinsWhenIdIsPresent(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/admin/demo/users' => $this->buildAction('get', RouterFlowController::class, 'GET'),
            'GET#|#/admin/demo/users/{id}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 0, ['id']),
        ]);

        $this->bootstrapRequest('/admin/demo/users/42', 'GET');
        $result = $router->execute('/admin/demo/users/42');

        $this->assertSame('cached:42', $result);
        $this->assertSame(['cached'], RouterFlowController::$calls);
    }

    public function testAdminManagerBaseRouteStillResolvesWithoutId(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/admin/demo/users' => $this->buildAction('get', RouterFlowController::class, 'GET'),
            'GET#|#/admin/demo/users/{id}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 0, ['id']),
        ]);

        $this->bootstrapRequest('/admin/demo/users', 'GET');
        $result = $router->execute('/admin/demo/users');

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

    public function testRouteParamIntegrityIsNotOverriddenByQueryString(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/integrity/{id}' => $this->buildAction('cached', RouterFlowController::class, 'GET', 0, ['id']),
        ]);

        $this->bootstrapRequest('/contract/integrity/42', 'GET', ['id' => '999']);
        $result = $router->execute('/contract/integrity/42');

        $this->assertSame('cached:42', $result);
    }

    public function testRestrictedRouteDenialStopsActionExecution(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/admin/private' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);
        $this->bootstrapRequest('/admin/private', 'GET');

        $adminsPath = CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json';
        $adminsBackup = file_exists($adminsPath) ? file_get_contents($adminsPath) : null;
        Cache::getInstance()->storeData($adminsPath, [
            'root' => [
                'hash' => sha1('root:secret'),
                'profile' => '889a3a791b3875cfae413574b53da4bb8a90d53e',
            ],
        ], Cache::JSONGZ, true);

        try {
            $method = new \ReflectionMethod(Router::class, 'executeMatchedRoute');
            $method->setAccessible(true);
            $action = $this->buildAction('get', RouterFlowController::class, 'GET');
            try {
                $method->invoke($router, '/admin/private', 'GET#|#/admin/private', $action);
                $this->fail('Expected AccessDeniedException was not thrown');
            } catch (AccessDeniedException) {
                $this->assertSame([], RouterFlowController::$calls);
            }
        } finally {
            if (null === $adminsBackup) {
                @unlink($adminsPath);
            } else {
                file_put_contents($adminsPath, $adminsBackup);
            }
        }
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

    public function testModulesTraitExtractDomainAndDomainExistsWithNormalizedInput(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([]);
        $this->setRouterPrivateProperty($router, 'domains', 'invalid');

        $extractDomain = new \ReflectionMethod(Router::class, 'extractDomain');
        $extractDomain->setAccessible(true);
        $extractDomain->invoke($router, new \ReflectionClass(RouterDomainContractController::class));

        $this->assertTrue($router->domainExists('contractdomain'));
        $this->assertFalse($router->domainExists('missingdomain'));
    }

    public function testLoadExternalAutoloaderIncludesModuleAndSupportsHydration(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([]);
        $this->setRouterPrivateProperty($router, 'finder', new \Symfony\Component\Finder\Finder());

        $tmpBase = CACHE_DIR . DIRECTORY_SEPARATOR . 'router_module_' . uniqid('', true);
        $externalModulePath = $tmpBase . DIRECTORY_SEPARATOR . 'src';
        $moduleName = 'TmpContractModule';
        $modulePath = $externalModulePath . DIRECTORY_SEPARATOR . $moduleName;
        $controllerDir = $modulePath . DIRECTORY_SEPARATOR . 'controller';
        @mkdir($controllerDir, 0777, true);

        $autoloadFile = $modulePath . DIRECTORY_SEPARATOR . 'autoload.php';
        file_put_contents(
            $autoloadFile,
            "<?php\n\$GLOBALS['psfs_contract_module_autoload_hits'] = (\$GLOBALS['psfs_contract_module_autoload_hits'] ?? 0) + 1;\n"
        );
        file_put_contents($controllerDir . DIRECTORY_SEPARATOR . 'SampleController.php', "<?php\n");
        $moduleInfo = new \Symfony\Component\Finder\SplFileInfo($modulePath, '', $moduleName);
        $routing = [];

        try {
            $method = new \ReflectionMethod(Router::class, 'loadExternalAutoloader');
            $method->setAccessible(true);
            $method->invokeArgs($router, [true, $moduleInfo, $externalModulePath, &$routing]);
            $this->assertGreaterThan(0, (int)($GLOBALS['psfs_contract_module_autoload_hits'] ?? 0));
            $this->assertIsArray($routing);
        } finally {
            unset($GLOBALS['psfs_contract_module_autoload_hits']);
            $this->deleteDirectoryRecursively($tmpBase);
        }
    }

    public function testLoadExternalModuleSwallowsFinderExceptions(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([]);
        $moduleName = 'psfs/router-contract-' . uniqid('', true);
        $modulePath = VENDOR_DIR . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'src';
        @mkdir($modulePath, 0777, true);
        $this->setRouterPrivateProperty($router, 'finder', new ThrowingDirectoriesFinder());

        $routing = [];
        try {
            $method = new \ReflectionMethod(Router::class, 'loadExternalModule');
            $method->setAccessible(true);
            $method->invokeArgs($router, [false, $moduleName, &$routing]);
            $this->assertSame([], $routing);
        } finally {
            $this->deleteDirectoryRecursively(VENDOR_DIR . DIRECTORY_SEPARATOR . $moduleName);
        }
    }

    public function testRouterCacheFlowHelpersCoverRootAndDebugBranches(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/debug' => $this->buildAction('get', RouterFlowController::class, 'GET'),
        ]);

        $config = $this->configBackup;
        $config['debug'] = false;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $normalizeHomeAction = new \ReflectionMethod(Router::class, 'normalizeHomeAction');
        $normalizeHomeAction->setAccessible(true);
        $this->assertNull($normalizeHomeAction->invoke($router, null));
        $this->assertNull($normalizeHomeAction->invoke($router, '  '));

        $specificity = new \ReflectionMethod(Router::class, 'calculateRouteSpecificity');
        $specificity->setAccessible(true);
        $this->assertSame(0, $specificity->invoke($router, '/'));

        $shouldRebuildRouting = new \ReflectionMethod(Router::class, 'shouldRebuildRouting');
        $shouldRebuildRouting->setAccessible(true);
        $this->assertFalse((bool)$shouldRebuildRouting->invoke($router));

        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'routes.meta.json', [], Cache::JSON, true);
        $isRoutingMetaFresh = new \ReflectionMethod(Router::class, 'isRoutingMetaFresh');
        $isRoutingMetaFresh->setAccessible(true);
        $this->assertFalse((bool)$isRoutingMetaFresh->invoke($router));
    }

    public function testExecuteCachedRouteLogsWhenControllerReturnsFalse(): void
    {
        $router = new TestableRouter();
        $router->seedRoutes([
            'GET#|#/contract/false' => $this->buildAction('returnsFalse', RouterFlowController::class, 'GET'),
        ]);
        $this->bootstrapRequest('/contract/false', 'GET');

        $result = $router->execute('/contract/false');

        $this->assertFalse($result);
        $this->assertSame(['returnsFalse'], RouterFlowController::$calls);
    }

    private function setRouterPrivateProperty(Router $router, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($router, $value);
    }

    private function deleteDirectoryRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $entries = scandir($path);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->deleteDirectoryRecursively($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
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

class StageTrackingRouter extends TestableRouter
{
    private static array $trace = [];

    public static function resetTrace(): void
    {
        self::$trace = [];
    }

    public static function getTrace(): array
    {
        return self::$trace;
    }

    protected function stageLoadContext(string $route): array
    {
        self::$trace[] = 'load';
        return parent::stageLoadContext($route);
    }

    protected function stageMatchRoute(string $path, string $httpRequest): array
    {
        self::$trace[] = 'match';
        return parent::stageMatchRoute($path, $httpRequest);
    }

    protected function stageExecuteRoute(string $route, string $pattern, array $action): mixed
    {
        self::$trace[] = 'execute';
        return parent::stageExecuteRoute($route, $pattern, $action);
    }

    protected function stageMapNotFoundException(RouterException $exception): RouterException
    {
        self::$trace[] = 'map';
        return parent::stageMapNotFoundException($exception);
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

    public function returnsFalse(): bool
    {
        self::$calls[] = 'returnsFalse';
        return false;
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

class RouterDomainContractController
{
    public const DOMAIN = 'ContractDomain';
}

class ThrowingDirectoriesFinder
{
    public function directories(): self
    {
        return $this;
    }

    public function in(string $path): self
    {
        throw new \Exception('finder-error-' . $path);
    }

    public function depth(int|string $depth): self
    {
        return $this;
    }

    public function hasResults(): bool
    {
        return false;
    }
}
