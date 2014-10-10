<?php

namespace PSFS\base\types;

use PSFS\base\Router;
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
     * @return $this
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
        $vars["__menu__"] = $this->getMenu();
        return $this->tpl->dump($this->getDomain() . $template, $vars);
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
        header("Content-length: " . strlen($response));
        echo $response;
        ob_flush();
        ob_end_clean();
        exit();
    }

    /**
     * Método que fuerza la descarga de un fichero
     * @param $data
     * @param string $content
     * @param string $filename
     * @return data
     */
    public function download($data, $content = "text/html", $filename = 'data.txt')
    {
        ob_start();
        header('Pragma: public');
        /////////////////////////////////////////////////////////////
        // prevent caching....
        /////////////////////////////////////////////////////////////
        // Date in the past sets the value to already have been expired.
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');    // HTTP/1.1
        header ("Pragma: no-cache");
        header("Expires: 0");
        header('Content-Transfer-Encoding: none');
        header("Content-type: " . $content);
        header("Content-length: " . strlen($data));
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $data;
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
     * @return \PSFS\base\Request
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

    /**
     * Wrapper para obtener la url de una ruta interna
     * @param string $route
     * @param bool $absolute
     * @param array $params
     *
     * @return mixed
     */
    public function getRoute($route = '', $absolute = false, $params = array())
    {
        return Router::getInstance()->getRoute($route, $absolute, $params);
    }

    /**
     * Wrapper que hace una redirección
     * @param $route
     * @param array $params
     *
     * @return mixed
     */
    public function redirect($route, $params = array())
    {
        return $this->getRequest()->redirect($this->getRoute($route, true, $params));
    }

}