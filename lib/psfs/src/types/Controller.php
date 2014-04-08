<?php

namespace PSFS\types;

use PSFS\types\interfaces\ControllerInterface;
use PSFS\base\Template;
use PSFS\base\Request;

/**
 * Class Controller
 * @package PSFS\types
 */
abstract class Controller extends \PSFS\base\Singleton implements ControllerInterface{

    protected $tpl;
    protected $domain = '';

    /**
     * Constructor por defecto
     */
    function __construct(){
        $this->tpl = Template::getInstance();
    }

    /**
     * Método que renderiza una plantilla
     * @param $template
     * @param array $vars
     *
     * @return mixed
     */
    public function render($template, array $vars = array())
    {
        return $this->tpl->render($this->getDomain() . $template, $vars);
    }

    /**
     * Método que devuelve un modelo
     * @param $model
     */
    public function getModel($model){}

    /**
     * Método que devuelve una respuesta con formato
     * @param $response
     * @param string $type
     */
    public function response($response, $type = "text/html")
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
    protected function getRequest(){ return Request::getInstance(); }

    /**
     * Método que añade la ruta del controlador a los path de plantillas Twig
     * @return mixed
     */
    protected function setTemplatePath($path)
    {
        $this->tpl->addPath($path, $this->domain);
        return $this;
    }

    /**
     * Método que setea el dominio del controlador para las plantillas
     * @param $domain
     *
     * @return $this
     */
    protected function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Método que devuelve el dominio del controlador
     * @return string
     */
    public function getDomain()
    {
        return "@{$this->domain}/";
    }

}