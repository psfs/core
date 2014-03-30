<?php

namespace PSFS\types;

use PSFS\types\interfaces\ControllerInterface;

/**
 * Class Controller
 * @package PSFS\types
 */
class Controller extends \PSFS\base\Singleton implements ControllerInterface{

    /**
     * Método que renderiza una plantilla
     * @param $template
     * @param array $vars
     *
     * @return mixed
     */
    public function render($template, array $vars = array())
    {
        return \PSFS\base\Template::getInstance()->render($template, $vars);
    }

    /**
     * Método que devuelve un modelo
     * @param $model
     */
    public function getModel($model)
    {

    }

    /**
     * Método que devuelve una respuesta con formato
     * @param $response
     * @param string $type
     */
    public function response(string $response, $type = "text/html")
    {
        ob_start();
        header("Content-type: " . $type);
        header("Content-length: " . count($response));
        echo $response;
        ob_flush();
        ob_end_clean();
        exit();
    }

    /**
     * Método que devuelve el objeto de petición
     * @return \PSFS\base\Singleton
     */
    protected function getRequest(){ return \PSFS\base\Request::getInstance(); }

}