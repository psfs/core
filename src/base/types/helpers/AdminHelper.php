<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Security;

/**
 * @package PSFS\base\types\helpers
 */
class AdminHelper
{

    /**
     * @param array $elementA
     * @param array $elementB
     * @return int
     */
    public static function sortByLabel(array $elementA, array $elementB): int
    {
        $labelA = array_key_exists('label', $elementA) ? $elementA['label'] : '';
        $labelB = array_key_exists('label', $elementB) ? $elementB['label'] : '';
        if ($labelA === $labelB) {
            return 0;
        }
        return $labelA < $labelB ? -1 : 1;
    }

    /**
     * @param array $systemRoutes
     * @return array
     */
    public static function getAdminRoutes(array $systemRoutes): array
    {
        if (Security::getInstance()->isUser()) {
            return [];
        }
        $routes = self::extractAdminRoutes($systemRoutes);
        self::sortRoutes($routes);
        return $routes;
    }

    /**
     * @param array $systemRoutes
     * @return array
     */
    protected static function extractAdminRoutes(array $systemRoutes): array
    {
        $routes = [];
        $excluded_modules = explode(',', strtoupper(Config::getParam('hide.modules', '')));
        foreach ($systemRoutes as $params) {
            $module = strtoupper($params['module']);
            if (in_array($module, $excluded_modules, true) && $module !== 'PSFS') {
                continue;
            }
            if (isset($params['http']) && Request::VERB_GET === $params['http']
                && preg_match('/^\/admin(\/|$)/', $params['default'])
                && !preg_match('/^\/admin\/[^\/]+\/[^\/]+\/\{id\}$/', $params['default'])
                && !in_array($params['slug'], ['n-a', 'admin-login', 'admin'], true)) {
                $mode = $params['visible'] ? 'visible' : 'hidden';
                $routes[$module][$mode][] = [
                    'slug' => $params['slug'],
                    'label' => self::resolveRouteLabel($params),
                    'icon' => $params['icon'],
                ];
            }
        }
        return $routes;
    }

    /**
     * @param array $routes
     */
    protected static function sortRoutes(array &$routes): void
    {
        foreach ($routes as &$route) {
            if (array_key_exists('visible', $route)) {
                uasort($route['visible'], '\PSFS\base\types\helpers\AdminHelper::sortByLabel');
            }
            if (array_key_exists('hidden', $route)) {
                uasort($route['hidden'], '\PSFS\base\types\helpers\AdminHelper::sortByLabel');
            }
        }
    }

    private static function resolveRouteLabel(array $params): string
    {
        $label = (string)($params['label'] ?? $params['slug'] ?? '');
        if ($label === '') {
            return '';
        }

        if (str_contains($label, '{__DOMAIN__}')) {
            $module = (string)($params['module'] ?? '');
            $label = str_replace('{__DOMAIN__}', $module, $label);
        }

        if (!str_contains($label, '{__API__}')) {
            return $label;
        }

        $defaultRoute = (string)($params['default'] ?? '');
        if (
            preg_match('/^\/admin\/[^\/]+\/([^\/]+)(?:\/.*)?$/', $defaultRoute, $matches) === 1
            && isset($matches[1])
            && $matches[1] !== ''
        ) {
            return str_replace('{__API__}', $matches[1], $label);
        }

        return $label;
    }
}
