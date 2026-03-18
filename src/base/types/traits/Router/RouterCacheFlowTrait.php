<?php

namespace PSFS\base\types\traits\Router;

use PSFS\base\Cache;
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
        if (empty($this->routing)) {
            return true;
        }
        if (!Config::getParam('debug', true)) {
            return false;
        }
        return !$this->isRoutingMetaFresh();
    }

    private function mapNotFoundException(RouterException $exception): RouterException
    {
        Logger::log($exception->getMessage(), LOG_WARNING);
        return new RouterException(t('Page not found'), $exception->getCode());
    }

    private function buildMatchContext(string $route): array
    {
        $parts = parse_url($route);
        $path = array_key_exists('path', $parts) ? $parts['path'] : $route;
        return [$path, Request::getInstance()->getMethod()];
    }

    private function findMatchingRoute(string $path, string $httpRequest): ?array
    {
        $bestExact = null;
        $bestExactScore = PHP_INT_MIN;
        $bestFallback = null;
        $bestFallbackScore = PHP_INT_MIN;
        foreach ($this->routing as $pattern => $action) {
            [$httpMethod, $routePattern] = RouterHelper::extractHttpRoute($pattern);
            if (!RouterHelper::matchRoutePattern($routePattern, $path) || !RouterHelper::compareSlashes($routePattern, $path)) {
                continue;
            }
            $score = $this->calculateRouteSpecificity($routePattern);
            if ($httpMethod === $httpRequest) {
                if ($score > $bestExactScore) {
                    $bestExact = [$pattern, $action];
                    $bestExactScore = $score;
                }
            }
            if ($httpMethod === 'ALL' && $score > $bestFallbackScore) {
                $bestFallback = [$pattern, $action];
                $bestFallbackScore = $score;
            }
        }
        return $bestExact ?? $bestFallback;
    }

    private function calculateRouteSpecificity(string $routePattern): int
    {
        $trimmed = trim($routePattern, '/');
        if ('' === $trimmed) {
            return 0;
        }
        $parts = explode('/', $trimmed);
        $total = count($parts);
        $dynamic = 0;
        $staticLen = 0;
        foreach ($parts as $part) {
            if (preg_match('/^\{[^}]+\}$/', $part)) {
                $dynamic++;
                continue;
            }
            $staticLen += strlen($part);
        }
        return ($total * 1000) + (($total - $dynamic) * 100) + $staticLen - ($dynamic * 10);
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

    private function isRoutingMetaFresh(): bool
    {
        $meta = $this->loadRoutingMeta();
        if (!is_array($meta) || empty($meta['fingerprint'])) {
            return false;
        }
        $currentFingerprint = $this->calculateRoutingFingerprint();
        return hash_equals((string)$meta['fingerprint'], (string)$currentFingerprint);
    }

    private function loadRoutingMeta(): array
    {
        $metaPath = CONFIG_DIR . DIRECTORY_SEPARATOR . self::ROUTING_META_FILE;
        $meta = $this->cache->getDataFromFile($metaPath, Cache::JSON, true);
        return is_array($meta) ? $meta : [];
    }

    private function storeRoutingMeta(): void
    {
        $metaPath = CONFIG_DIR . DIRECTORY_SEPARATOR . self::ROUTING_META_FILE;
        $payload = [
            'fingerprint' => $this->calculateRoutingFingerprint(),
            'updated_at' => date('c'),
        ];
        $this->cache->storeData($metaPath, $payload, Cache::JSON, true);
    }
}
