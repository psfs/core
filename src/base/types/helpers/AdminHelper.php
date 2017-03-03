<?php
namespace PSFS\base\types\helpers;

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
        if ($labelA == $labelB) {
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
        $routes = [];
        foreach ($systemRoutes as $route => $params) {
            if('GET' === $params['http'] && preg_match('/^\/admin(\/|$)/', $params['default'])) {
                $module = strtoupper($params['module']);
                $mode = $params["visible"] ? 'visible' : 'hidden';
                $routes[$module][$mode][] = [
                    'slug' => $params["slug"],
                    'label' => $params["label"] ?: $params["slug"],
                    'icon' => $params['icon'],
                ];
            }
        }
        foreach($routes as $module => &$route) {
            if(array_key_exists('visible', $route)) {
                uasort($route["visible"], '\PSFS\base\types\helpers\AdminHelper::sortByLabel');
            }
            if(array_key_exists('hidden', $route)) {
                uasort($route["hidden"], '\PSFS\base\types\helpers\AdminHelper::sortByLabel');
            }
        }
        return $routes;
    }
}