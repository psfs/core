<?php
namespace PSFS\base\types\helpers;

/**
 * Class AdminHelper
 * @package PSFS\base\types\helpers
 */
class AdminHelper {

    /**
     * @param array $elementA
     * @param array $elementB
     * @return int
     */
    public static function sortByLabel(array $elementA, array $elementB) {
        $labelA = array_key_exists('label', $elementA) ? $elementA['label'] : '';
        $labelB = array_key_exists('label', $elementB) ? $elementB['label'] : '';
        if($labelA == $labelB) {
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
        $routes = array();
        foreach ($systemRoutes as $route => $params) {
            list($httpMethod, $routePattern) = RouterHelper::extractHttpRoute($route);
            if (preg_match('/^\/admin(\/|$)/', $routePattern)) {
                if (preg_match('/^\\\?PSFS/', $params["class"])) {
                    $profile = "superadmin";
                } else {
                    $profile = "admin";
                }
                if (!empty($params["default"]) && preg_match('/(GET|ALL)/i', $httpMethod)) {
                    $_profile = ($params["visible"]) ? $profile : 'adminhidden';
                    if (!array_key_exists($_profile, $routes)) {
                        $routes[$_profile] = array();
                    }
                    $routes[$_profile][] = [
                        'slug' => $params["slug"],
                        'label' => $params["label"] ?: $params["slug"]
                    ];
                }
            }
        }
        if (array_key_exists("superadmin", $routes)) {
            uasort($routes["superadmin"], 'AdminHelper::sortByLabel');
        }
        if (array_key_exists("adminhidden", $routes)) {
            asort($routes["adminhidden"], 'AdminHelper::sortByLabel');
        }
        if (array_key_exists('admin', $routes)) {
            asort($routes["admin"], 'AdminHelper::sortByLabel');
        }
        return $routes;
    }
}