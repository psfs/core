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
use PSFS\base\types\Controller;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\interfaces\PreConditionedRunInterface;
use PSFS\base\types\traits\RouteCheckTrait;
use PSFS\base\types\traits\Router\ModulesTrait;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\controller\base\Admin;
use ReflectionException;

/**
 * Class Router
 * @package PSFS
 */
class Router
{
    use SingletonTrait;
    use ModulesTrait;
    use RouteCheckTrait;

    const PSFS_BASE_NAMESPACE = 'PSFS';
    /**
     * @var Cache $cache
     */
    private $cache;
    /**
     * @var int
     */
    protected $cacheType = Cache::JSON;

    /**
     * Router constructor.
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
        list($this->routing, $this->slugs) = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json', $this->cacheType, TRUE);
        $this->domains = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', $this->cacheType, TRUE);
        if (empty($this->routing) || Config::getParam('debug', true)) {
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
            Logger::log('End routes load');
        } else {
            Logger::log('Routes generation skipped');
        }
    }

    /**
     * @param string|null $route
     *
     * @return string HTML
     * @throws Exception
     */
    public function execute($route)
    {
        Inspector::stats('[Router] Executing the request', Inspector::SCOPE_DEBUG);
        try {
            //Search action and execute
            return $this->searchAction($route);
        } catch (AccessDeniedException $e) {
            Logger::log(t('Solicitamos credenciales de acceso a zona restringida'), LOG_WARNING, ['file' => $e->getFile() . '[' . $e->getLine() . ']']);
            return Admin::staticAdminLogon();
        } catch (RouterException $r) {
            Logger::log($r->getMessage(), LOG_WARNING);
            $code = $r->getCode();
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }

        throw new RouterException(t('Página no encontrada'), $code);
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
        //Revisamos si tenemos la ruta registrada
        $parts = parse_url($route);
        $path = array_key_exists('path', $parts) ? $parts['path'] : $route;
        $httpRequest = Request::getInstance()->getMethod();
        foreach ($this->routing as $pattern => $action) {
            list($httpMethod, $routePattern) = RouterHelper::extractHttpRoute($pattern);
            $matched = RouterHelper::matchRoutePattern($routePattern, $path);
            if ($matched && ($httpMethod === 'ALL' || $httpRequest === $httpMethod) && RouterHelper::compareSlashes($routePattern, $path)) {
                self::setCheckedRoute($action);
                // Checks restricted access
                SecurityHelper::checkRestrictedAccess($route);
                $get = RouterHelper::extractComponents($route, $routePattern);
                /** @var $class Controller */
                $class = RouterHelper::getClassToCall($action);
                try {
                    if ($this->checkRequirements($action, $get)) {
                        return $this->executeCachedRoute($route, $action, $class, $get);
                    }

                    throw new RouterException(t('Preconditions failed'), 412);
                } catch (Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
                    throw $e;
                }
            }
        }
        throw new RouterException(t('Ruta no encontrada'));
    }

    /**
     * @param array $action
     * @param array $params
     * @return bool
     */
    private function checkRequirements(array $action, $params = [])
    {
        Inspector::stats('[Router] Checking request requirements', Inspector::SCOPE_DEBUG);
        if (!empty($params) && !empty($action['requirements'])) {
            $checked = 0;
            foreach (array_keys($params) as $key) {
                if (in_array($key, $action['requirements'], true) && strlen($params[$key])) {
                    $checked++;
                }
            }
            $valid = count($action['requirements']) === $checked;
        } else {
            $valid = true;
        }
        return $valid;
    }

    /**
     * @throws ConfigException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws GeneratorException
     */
    private function generateRouting()
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
        $this->cache->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', $this->domains, Cache::JSON, TRUE);
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
        $home = Config::getParam('home.action', 'admin');
        if (NULL !== $home || $home !== '') {
            $homeParams = NULL;
            foreach ($this->routing as $pattern => $params) {
                list($method, $route) = RouterHelper::extractHttpRoute($pattern);
                if (preg_match('/' . preg_quote($route, '/') . '$/i', '/' . $home)) {
                    $homeParams = $params;
                }
                unset($method);
            }
            if (NULL !== $homeParams) {
                $this->routing['/'] = $homeParams;
            }
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
            throw new RouterException(t('No existe la ruta especificada'));
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
     * @param string $class
     * @param string $method
     */
    private function checkPreActions($class, $method)
    {
        if ($this->hasToRunPreChecks($class)) {
            self::run($class, '__check', true);
        }
        $preAction = 'pre' . ucfirst($method);
        if (method_exists($class, $preAction)) {
            self::run($class, $preAction);
        }
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
     * Check if class to run route implements the PreConditionedRunInterface
     * @param string $class
     * @return bool
     */
    private function hasToRunPreChecks($class)
    {
        return in_array(PreConditionedRunInterface::class, class_implements($class));
    }

    /**
     * @param string $route
     * @param array $action
     * @param string $class
     * @param array $params
     * @return mixed
     * @throws GeneratorException
     * @throws ConfigException
     */
    protected function executeCachedRoute($route, $action, $class, $params = NULL)
    {
        Inspector::stats('[Router] Executing route ' . $route, Inspector::SCOPE_DEBUG);
        $action['params'] = array_merge($action['params'], $params, Request::getInstance()->getQueryParams());
        Security::getInstance()->setSessionKey(Cache::CACHE_SESSION_VAR, $action);
        $cache = Cache::needCache();
        $execute = TRUE;
        $return = null;
        if (FALSE !== $cache && $action['http'] === 'GET' && Config::getParam('debug') === FALSE) {
            list($path, $cacheDataName) = $this->cache->getRequestCacheHash();
            $cachedData = $this->cache->readFromCache('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName, $cache);
            if (NULL !== $cachedData) {
                $headers = $this->cache->readFromCache('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName . '.headers', $cache, null, Cache::JSON);
                Template::getInstance()->renderCache($cachedData, $headers);
                $execute = FALSE;
            }
        }
        if ($execute) {
            Inspector::stats('[Router] Start executing action ' . $route, Inspector::SCOPE_DEBUG);
            $this->checkPreActions($class, $action['method']);
            $return = call_user_func_array([$class, $action['method']], $params);
            if (false === $return) {
                Logger::log(t('An error occurred trying to execute the action'), LOG_ERR, [error_get_last()]);
            }
        }
        return $return;
    }

    /**
     * @param Exception|null $exception
     * @param bool $isJson
     * @return string
     * @throws GeneratorException
     */
    public function httpNotFound(\Exception $exception = null, $isJson = false)
    {
        return ResponseHelper::httpNotFound($exception, $isJson);
    }
}
