<?php

namespace PSFS\base\types\traits;

trait RouteCheckTrait
{
    /**
     * @var array
     */
    private static array $route = [];

    public static function getCheckedRoute(): array
    {
        return self::$route;
    }

    public static function setCheckedRoute($route): void
    {
        self::$route = self::normalizeCheckedRoute($route);
    }

    private static function normalizeCheckedRoute(mixed $route): array
    {
        if (is_array($route)) {
            return $route;
        }
        if (null === $route || $route === '') {
            return [];
        }
        return [$route];
    }
}
