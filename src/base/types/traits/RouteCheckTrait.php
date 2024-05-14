<?php

namespace PSFS\base\types\traits;

trait RouteCheckTrait {
    /**
     * @var array
     */
    private static $route;

    public static function getCheckedRoute(): array
    {
        return self::$route;
    }

    public static function setCheckedRoute($route): void
    {
        self::$route = $route;
    }
}