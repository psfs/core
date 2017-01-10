<?php

namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\exception\RouterException;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Singleton;
use PSFS\base\types\interfaces\ControllerInterface;

/**
 * Class Controller
 * @package PSFS\base\types
 */
abstract class Controller extends Singleton implements ControllerInterface
{

    /**
     * @Inyectable
     * @var \PSFS\base\Template $tpl
     */
    protected $tpl;
    protected $domain = '';

    /**
     * Método que renderiza una plantilla
     * @param string $template
     * @param array $vars
     * @param array $cookies
     * @param string $domain
     *
     * @return string HTML
     */
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        $vars['__menu__'] = $this->getMenu();
        $domain = (null ===$domain) ? $this->getDomain() : $domain;
        return $this->tpl->render($domain . $template, $vars, $cookies);
    }

    /**
     * Método del controlador que añade los menús automáticamente a las vistas
     * @return array
     */
    protected function getMenu()
    {
        return array();
    }

    public function init()
    {
        parent::init();
        $this->setDomain($this->domain)
            ->setTemplatePath(Config::getInstance()->getTemplatePath());
    }

    /**
     * Método que renderiza una plantilla
     * @param string $template
     * @param array $vars
     * @param string $domain
     *
     * @return string
     */
    public function dump($template, array $vars = array(), $domain = null)
    {
        $vars['__menu__'] = $this->getMenu();
        $domain = $domain ?: $this->getDomain();
        return $this->tpl->dump($domain . $template, $vars);
    }

    /**
     * Método que devuelve una respuesta con formato
     * @param string $response
     * @param string $type
     */
    public function response($response, $type = 'text/html')
    {
        $this->tpl->output($response, $type);
    }

    /**
     * Método que fuerza la descarga de un fichero
     * @param $data
     * @param string $content
     * @param string $filename
     * @return mixed
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
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
        header('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
        header("Pragma: no-cache");
        header("Expires: 0");
        header('Content-Transfer-Encoding: none');
        header("Content-type: " . $content);
        header("Content-length: " . strlen($data));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $data;
        ob_flush();
        ob_end_clean();
        exit;
    }

    /**
     * Método que devuelve una salida en formato JSON
     * @param mixed $response
     * @param int $statusCode
     *
     * @return string JSON
     */
    public function json($response, $statusCode = 200)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        $this->tpl->setStatus($statusCode);
        return $this->response($data, "application/json");
    }

    /**
     * Método que devuelve una salida en formato JSON
     * @param mixed $response
     */
    public function jsonp($response)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        $this->response($data, "application/javascript");
    }

    /**
     * Método que devuelve el objeto de petición
     * @return \PSFS\base\Request
     */
    protected function getRequest()
    {
        return Request::getInstance();
    }

    /**
     * Método que añade la ruta del controlador a los path de plantillas Twig
     * @param string $path
     * @return $this
     */
    protected function setTemplatePath($path)
    {
        $this->tpl->addPath($path, $this->domain);
        return $this;
    }

    /**
     * Método que setea el dominio del controlador para las plantillas
     * @param string $domain
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
     * @return string|null
     * @throws RouterException
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
     * @return mixed
     * @throws RouterException
     */
    public function redirect($route, array $params = array())
    {
        return $this->getRequest()->redirect($this->getRoute($route, true, $params));
    }

}
