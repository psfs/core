<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Request;

/**
 * Class AdminHelper
 * @package PSFS\base\types\helpers
 */
class AdminHelper
{

    /**
     * @param array $elementA
     * @param array $elementB
     * @return int
     */
    public static function sortByLabel(array $elementA, array $elementB)
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
    public static function getAdminRoutes(array $systemRoutes)
    {
        $routes = self::extractAdminRoutes($systemRoutes);
        self::sortRoutes($routes);
        return $routes;
    }

    /**
     * @param array $systemRoutes
     * @return array
     */
    protected static function extractAdminRoutes(array $systemRoutes)
    {
        $routes = [];
        foreach ($systemRoutes as $params) {
            if (isset($params['http']) && Request::VERB_GET === $params['http']
                && preg_match('/^\/admin(\/|$)/', $params['default'])
                && !in_array($params['slug'], ['n-a', 'admin-login', 'admin'])) {
                $module = strtoupper($params['module']);
                $mode = $params['visible'] ? 'visible' : 'hidden';
                $routes[$module][$mode][] = [
                    'slug' => $params['slug'],
                    'label' => $params['label'] ?: $params['slug'],
                    'icon' => $params['icon'],
                ];
            }
        }
        return $routes;
    }

    /**
     * @param array $routes
     */
    protected static function sortRoutes(array &$routes)
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
}
