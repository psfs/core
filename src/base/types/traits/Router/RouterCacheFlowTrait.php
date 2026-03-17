<?php

namespace PSFS\base\types\traits\Router;

use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\RouterHelper;

trait RouterCacheFlowTrait
{
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
            return count($action['requirements']) === $checked;
        }
        return true;
    }

    private function loadRoutingCache(): array
    {
        $cached = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json', $this->cacheType, true);
        if (is_array($cached) && count($cached) === 2) {
            return [$cached[0] ?: [], $cached[1] ?: []];
        }
        return [[], []];
    }

    private function loadDomainsCache(): array
    {
        $domains = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', $this->cacheType, true);
        return is_array($domains) ? $domains : [];
    }

    private function shouldRebuildRouting(): bool
    {
        return empty($this->routing) || Config::getParam('debug', true);
    }

    private function mapNotFoundException(RouterException $exception): RouterException
    {
        Logger::log($exception->getMessage(), LOG_WARNING);
        return new RouterException(t('Página no encontrada'), $exception->getCode());
    }

    private function buildMatchContext(string $route): array
    {
        $parts = parse_url($route);
        $path = array_key_exists('path', $parts) ? $parts['path'] : $route;
        return [$path, Request::getInstance()->getMethod()];
    }

    private function findMatchingRoute(string $path, string $httpRequest): ?array
    {
        $fallback = null;
        foreach ($this->routing as $pattern => $action) {
            [$httpMethod, $routePattern] = RouterHelper::extractHttpRoute($pattern);
            if (!RouterHelper::matchRoutePattern($routePattern, $path) || !RouterHelper::compareSlashes($routePattern, $path)) {
                continue;
            }
            if ($httpMethod === $httpRequest) {
                return [$pattern, $action];
            }
            if ($httpMethod === 'ALL' && null === $fallback) {
                $fallback = [$pattern, $action];
            }
        }
        return $fallback;
    }

    private function normalizeHomeAction(mixed $home): ?string
    {
        if (null === $home) {
            return null;
        }
        $value = trim((string)$home);
        return '' === $value ? null : $value;
    }

    private function resolveHomeRouteParams(string $home): ?array
    {
        $homeParams = null;
        foreach ($this->routing as $pattern => $params) {
            [, $route] = RouterHelper::extractHttpRoute($pattern);
            if (preg_match('/' . preg_quote($route, '/') . '$/i', '/' . $home)) {
                $homeParams = $params;
            }
        }
        return is_array($homeParams) ? $homeParams : null;
    }
}
