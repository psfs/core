<?php
namespace PSFS\base\types\traits\Router;

/**
 * Trait RoutingTrait
 * @package PSFS\base\types\traits\Router
 */
trait RoutingTrait {
    /**
     * @var array
     */
    private $routing = [];

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routing;
    }

    /**
     * Method that extract all routes in the platform
     * @return array
     */
    public function getAllRoutes()
    {
        $routes = [];
        foreach ($this->getRoutes() as $path => $route) {
            if (array_key_exists('slug', $route)) {
                $routes[$route['slug']] = $path;
            }
        }
        return $routes;
    }

}
