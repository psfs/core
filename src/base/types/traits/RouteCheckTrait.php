<?php

namespace PSFS\base\types\traits;

trait RouteCheckTrait {
    /**
     * @var array
     */
    private static $route;

    public static function getRoute() {
        return self::$route;
    }

    public static function setRoute($route): void
    {
        self::$route = $route;
    }
}