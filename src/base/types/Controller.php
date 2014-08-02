<?php

namespace PSFS\base\types;

use PSFS\base\types\interfaces\ControllerInterface;
use PSFS\base\Template;
use PSFS\base\Request;

/**
 * Class Controller
 * @package PSFS\base\types
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
    public function render($template, array $vars = array(), $cookies = false)
    {
        $this->saveDomain();
        $vars["__menu__"] = $this->getMenu();
        return $this->tpl->render($this->getDomain() . $template, $vars, $cookies);
    }

    /**
     * Método del controlador que añade los menús automáticamente a las vistas
     * @return array
     */
    protected function getMenu()
    {
        return array();
    }

    /**
     * Método que renderiza una plantilla
     * @param $template
     * @param array $vars
     *
     * @return mixed
     */
    public function dump($template, array $vars = array())
    {
        $this->saveDomain();
        $vars["__menu__"] = $this->getMenu();
        return $this->tpl->dump($this->getDomain() . $template, $vars);
    }

    /**
     * Método que almacena los dominios en la carpetade configuración para poder parsear las traducciones
     * @return $this
     */
    protected function saveDomain()
    {
        $domains = array();
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json")) $domains = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json"), true);
        $domains[$this->getDomain()] = $this->tpl->getLoader()->getPaths();
        file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", json_encode($domains));
        return $this;
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
     * Método que devuelve una salida en formato JSON
     * @param $response
     */
    public function json($response)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        return $this->response($data, "application/json");
    }

    /**
     * Método que devuelve una salida en formato JSON
     * @param $response
     */
    public function jsonp($response)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        return $this->response($data, "application/javascript");
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