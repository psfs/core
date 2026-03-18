<?php

namespace PSFS\base;

use Exception;
use InvalidArgumentException;
use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\RouteCheckTrait;
use PSFS\base\types\traits\Router\ModulesTrait;
use PSFS\base\types\traits\Router\RouterCacheFlowTrait;
use PSFS\base\types\traits\Router\RouterExecutionTrait;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\controller\base\Admin;
use ReflectionException;

/**
 * @package PSFS
 */
class Router
{
    use SingletonTrait;
    use ModulesTrait;
    use RouteCheckTrait;
    use RouterCacheFlowTrait;
    use RouterExecutionTrait;

    const PSFS_BASE_NAMESPACE = 'PSFS';
    private const ROUTING_META_FILE = 'routes.meta.json';

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var int
     */
    protected $cacheType = Cache::JSON;

    /**
     * @throws GeneratorException
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->initializeFinder();
        $this->init();
    }

    /**
     * @throws GeneratorException
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function init()
    {
        [$this->routing, $this->slugs] = $this->loadRoutingCache();
        $this->domains = $this->loadDomainsCache();
        if ($this->shouldRebuildRouting()) {
            $this->debugLoad();
        }
        $this->checkExternalModules(false);
        $this->setLoaded();
    }

    /**
     * @throws GeneratorException
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    private function debugLoad()
    {
        if (!Config::getParam('skip.route_generation', false)) {
            Logger::log('Begin routes load');
            $this->hydrateRouting();
            $this->simpatize();
            $this->storeRoutingMeta();
            Logger::log('End routes load');
        } else {
            Logger::log('Routes generation skipped');
        }
    }

    /**
     * @param string|null $route
     *
     * @return string
     * @throws Exception
     */
    public function execute($route)
    {
        Inspector::stats('[Router] Executing the request', Inspector::SCOPE_DEBUG);
        try {
            return $this->searchAction($route);
        } catch (AccessDeniedException $e) {
            Logger::log(t('Requesting credentials for restricted area access'), LOG_WARNING, ['file' => $e->getFile() . '[' . $e->getLine() . ']']);
            return Admin::staticAdminLogon();
        } catch (RouterException $r) {
            throw $this->stageMapNotFoundException($r);
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }
    }

    /**
     * @param string $route
     * @return mixed
     * @throws AccessDeniedException
     * @throws AdminCredentialsException
     * @throws RouterException
     * @throws Exception
     */
    protected function searchAction($route)
    {
        Inspector::stats('[Router] Searching action to execute: ' . $route, Inspector::SCOPE_DEBUG);
        [$path, $httpRequest] = $this->stageLoadContext((string)$route);
        [$pattern, $action] = $this->stageMatchRoute($path, $httpRequest);
        return $this->stageExecuteRoute((string)$route, $pattern, $action);
    }

    /**
     * Stage 1: normalize runtime context for route lookup.
     *
     * @param string $route
     * @return array{0:string,1:string}
     */
    protected function stageLoadContext(string $route): array
    {
        return $this->buildMatchContext($route);
    }

    /**
     * Stage 2: resolve a route pattern + action pair or fail with RouterException.
     *
     * @param string $path
     * @param string $httpRequest
     * @return array{0:string,1:array}
     * @throws RouterException
     */
    protected function stageMatchRoute(string $path, string $httpRequest): array
    {
        $matchedRoute = $this->findMatchingRoute($path, $httpRequest);
        if (null === $matchedRoute) {
            throw new RouterException(t('Route not found'));
        }
        return $matchedRoute;
    }

    /**
     * Stage 3: execute the matched route action with preconditions and cache flow.
     *
     * @param string $route
     * @param string $pattern
     * @param array $action
     * @return mixed
     * @throws Exception
     */
    protected function stageExecuteRoute(string $route, string $pattern, array $action): mixed
    {
        return $this->executeMatchedRoute($route, $pattern, $action);
    }

    /**
     * Stage 4: map route-related exceptions to the public not-found contract.
     *
     * @param RouterException $exception
     * @return RouterException
     */
    protected function stageMapNotFoundException(RouterException $exception): RouterException
    {
        return $this->mapNotFoundException($exception);
    }

    /**
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws GeneratorException
     */
    protected function generateRouting()
    {
        $base = SOURCE_DIR;
        $modulesPath = realpath(CORE_DIR);
        $this->routing = $this->inspectDir($base, 'PSFS', array());
        $this->checkExternalModules();
        if (file_exists($modulesPath)) {
            $modules = $this->finder->directories()->in($modulesPath)->depth(0);
            if ($modules->hasResults()) {
                foreach ($modules->getIterator() as $modulePath) {
                    $module = $modulePath->getBasename();
                    $this->routing = $this->inspectDir($modulesPath . DIRECTORY_SEPARATOR . $module, $module, $this->routing);
                }
            }
        }
        $this->cache->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', $this->domains, Cache::JSON, true);
    }

    /**
     * @throws GeneratorException
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function hydrateRouting()
    {
        $this->generateRouting();
        $home = $this->normalizeHomeAction(Config::getParam('home.action', 'admin'));
        if (null === $home) {
            return;
        }
        $homeParams = $this->resolveHomeRouteParams($home);
        if (null !== $homeParams) {
            $this->routing['/'] = $homeParams;
        }
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public static function exists($namespace)
    {
        return (class_exists($namespace) || interface_exists($namespace) || trait_exists($namespace));
    }

    /**
     * @param string $slug
     * @param boolean $absolute
     * @param array $params
     *
     * @return string|null
     * @throws RouterException
     */
    public function getRoute($slug = '', $absolute = false, array $params = [])
    {
        $baseUrl = $absolute ? Request::getInstance()->getRootUrl() : '';
        if ('' === $slug) {
            return $baseUrl . '/';
        }
        if (!is_array($this->slugs) || !array_key_exists($slug, $this->slugs)) {
            throw new RouterException(t('Specified route does not exist'));
        }
        $url = $baseUrl . $this->slugs[$slug];
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        } elseif (!empty($this->routing[$this->slugs[$slug]]['default'])) {
            $url = $baseUrl . $this->routing[$this->slugs[$slug]]['default'];
        }

        return preg_replace('/(GET|POST|PUT|DELETE|ALL|HEAD|PATCH)\#\|\#/', '', $url);
    }

    /**
     * @param $class
     * @param string $method
     * @param boolean $throwExceptions
     * @return void
     * @throws Exception
     */
    public static function run($class, $method, $throwExceptions = false): void
    {
        Inspector::stats("[Router] Pre action invoked " . get_class($class) . "::{$method}", Inspector::SCOPE_DEBUG);
        try {
            if (false === call_user_func_array([$class, $method], [])) {
                Logger::log(t("[Router] action " . get_class($class) . "::{$method} failed"), LOG_ERR, [error_get_last()]);
                error_clear_last();
            }
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR, [$class, $method]);
            if ($throwExceptions) {
                throw $e;
            }
        }
    }

    /**
     * @param Exception|null $exception
     * @param bool $isJson
     * @return string
     * @throws GeneratorException
     */
    public function httpNotFound(\Throwable $exception = null, $isJson = false)
    {
        return ResponseHelper::httpNotFound($exception, $isJson);
    }
}
