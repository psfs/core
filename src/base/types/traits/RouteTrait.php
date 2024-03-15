<?php

namespace PSFS\base\types\traits;

use PSFS\base\Request;
use PSFS\base\Router;

/**
 * Class RouteTrait
 * @package PSFS\base\types
 */
trait RouteTrait
{

    use BoostrapTrait;

    /**
     * Wrapper para obtener la url de una ruta interna
     * @param string $route
     * @param bool $absolute
     * @param array $params
     * @return string|null
     * @throws \PSFS\base\exception\RouterException
     */
    public function getRoute($route = '', $absolute = false, array $params = array())
    {
        return Router::getInstance()->getRoute($route, $absolute, $params);
    }

    /**
     * Wrapper que hace una redirección
     * @param string $route
     * @param array $params
     *
     * @throws \PSFS\base\exception\RouterException
     */
    public function redirect($route, array $params = array())
    {
        Request::getInstance()->redirect($this->getRoute($route, true, $params));
    }

    /**
     * Método que devuelve el objeto de petición
     * @return \PSFS\base\Request
     */
    protected function getRequest()
    {
        return Request::getInstance();
    }
}
