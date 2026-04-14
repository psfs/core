<?php

namespace PSFS\base\types\traits\Router;

use Exception;
use PSFS\base\Cache;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\types\Controller;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\interfaces\PreConditionedRunInterface;

trait RouterExecutionTrait
{
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
     * @param string $class
     * @return bool
     */
    private function hasToRunPreChecks($class)
    {
        $implemented = class_implements($class);
        if (false === $implemented) {
            return false;
        }
        return in_array(PreConditionedRunInterface::class, $implemented, true);
    }

    /**
     * @param string $route
     * @param array $action
     * @param string $class
     * @param array $params
     * @return mixed
     */
    protected function executeCachedRoute($route, $action, $class, $params = null)
    {
        Inspector::stats('[Router] Executing route ' . $route, Inspector::SCOPE_DEBUG);
        $params = is_array($params) ? $params : [];
        $actionParams = is_array($action['params'] ?? null) ? $action['params'] : [];
        $action['params'] = array_merge($actionParams, $params, Request::getInstance()->getQueryParams());
        Security::getInstance()->setSessionKey(Cache::CACHE_SESSION_VAR, $action);
        $cache = Cache::needCache();
        $execute = true;
        $return = null;
        if (false !== $cache && $action['http'] === 'GET' && Config::getParam('debug') === false) {
            list($path, $cacheDataName) = $this->cache->getRequestCacheHash();
            $cachedData = $this->cache->readFromCache('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName, $cache);
            if (null !== $cachedData) {
                $headers = $this->cache->readFromCache(
                    'json' . DIRECTORY_SEPARATOR . $path . $cacheDataName . '.headers',
                    $cache,
                    null,
                    Cache::JSON
                );
                Template::getInstance()->renderCache($cachedData, $headers);
                $execute = false;
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
     * @param string $route
     * @param string $pattern
     * @param array $action
     * @return mixed
     * @throws Exception
     */
    private function executeMatchedRoute(string $route, string $pattern, array $action): mixed
    {
        [, $routePattern] = RouterHelper::extractHttpRoute($pattern);
        self::setCheckedRoute($action);
        SecurityHelper::checkRestrictedAccess($route);
        $params = RouterHelper::extractComponents($route, $routePattern);

        $class = RouterHelper::getClassToCall($action);
        try {
            if ($this->checkRequirements($action, $params)) {
                return $this->executeCachedRoute($route, $action, $class, $params);
            }
            throw new RouterException(t('Preconditions failed'), 412);
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }
    }
}
