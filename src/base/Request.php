<?php

namespace PSFS\base;

use PSFS\base\types\SingletonTrait;

if(!function_exists("getallheaders"))
{
    function getallheaders()
    {
        $headers = array();
        foreach($_SERVER as $h=>$v)
            if(preg_match('/HTTP_(.+)/',$h,$hp))
                $headers[$hp[1]]=$v;
        return $headers;
    }
}

/**
 * Class Request
 * @package PSFS
 */
class Request {
    use SingletonTrait;
    protected $server;
    protected $cookies;
    protected $upload;
    protected $header;
    protected $data;

    /**
     */
    public function __construct(){
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->upload = $_FILES;
        $this->header = $this->parseHeaders();
        $this->data = $_REQUEST;
        $contentType = (array_key_exists('Content-Type', $this->header)) ? $this->header['Content-Type'] : "text/html";
        if(preg_match('/application\/json/i', $contentType))
        {
            $this->data += json_decode(file_get_contents("php://input"), true);
        }
    }

    /**
     * Método que devuelve las cabeceras de la petición
     * @return array
     */
    private function parseHeaders(){ return getallheaders(); }

    /**
     * Método que verifica si existe una cabecera concreta
     * @param $header
     *
     * @return bool
     */
    public function hasHeader($header){ return (isset($this->header[$header])); }


    /**
     * Método que indica si una petición tiene cookies
     * @return bool
     */
    public function hasCookies(){ return (!empty($this->cookies)); }

    /**
     * Método que indica si una petición tiene cookies
     * @return bool
     */
    public function hasUpload(){ return (!empty($this->upload)); }

    /**
     * Método que devuelve el TimeStamp de la petición
     *
     * @param bool $formatted
     *
     * @return mixed
     */
    public static function ts($formatted = false){ return self::getInstance()->getTs($formatted); }
    public function getTs($formatted = false){ return ($formatted) ? date('Y-m-d H:i:s',$this->server["REQUEST_TIME_FLOAT"]) : $this->server["REQUEST_TIME_FLOAT"]; }

    /**
     * Método que devuelve el Método HTTP utilizado
     * @return string
     */
    public function getMethod(){ return strtoupper($this->server["REQUEST_METHOD"]); }

    /**
     * Método que devuelve una cabecera de la petición si existe
     * @param $name
     *
     * @return null
     */
    public static function header($name){ return self::getInstance()->getHeader($name); }
    public function getHeader($name)
    {
        $header = null;
        if($this->hasHeader($name))
        {
            $header = $this->header[$name];
        }
        return $header;
    }

    /**
     * Método que devuelve la url solicitada
     * @return mixed
     */
    public static function requestUri(){ return self::getInstance()->getrequestUri(); }
    public function getrequestUri(){ return $this->server["REQUEST_URI"]; }

    /**
     * Método que devuelve el idioma de la petición
     * @return string
     */
    public function getLanguage()
    {
        return array_key_exists("HTTP_ACCEPT_LANGUAGE", $this->server) ? $this->server["HTTP_ACCEPT_LANGUAGE"] : "es_ES";
    }

    /**
     * Método que determina si se ha solicitado un fichero
     * @return bool
     */
    public function isFile()
    {
        $file = (preg_match('/\.(css|js|png|jpg|jpeg|woff|ttf|svg|eot|xml|bmp|gif|txt|zip|yml|ini|conf|php|ico)$/', $this->getrequestUri()) != 0);
        return $file;
    }

    /**
     * Método que devuelve un parámetro de la solicitud
     * @param string $param
     *
     * @return string|null
     */
    public function get($param)
    {
        return (array_key_exists($param, $this->data)) ? $this->data[$param] : null;
    }

    /**
     * Método que devuelve todo los datos del Request
     * @return mixed
     */
    public function getData(){ return $this->data; }

    /**
     * Método que realiza una redirección a la url dada
     * @param string $url
     */
    public function redirect($url = null)
    {
        if(empty($url)) $url = $this->server['HTTP_ORIGIN'];
        ob_start();
        header('Location: ' . $url);
        ob_end_clean();
        exit(_("Redireccionando..."));
    }

    /**
     * Devuelve un parámetro de $_SERVER
     * @param $param
     *
     * @return null
     */
    public function getServer($param)
    {
        return array_key_exists($param, $this->server) ? $this->server[$param] : null;
    }

    /**
     * Devuelve el nombre del servidor
     * @return null
     */
    public function getServerName()
    {
        return $this->getServer("SERVER_NAME");
    }

    /**
     * Devuelve el protocolo de la conexión
     * @return string
     */
    public function getProtocol()
    {
        return ($this->getServer("HTTPS") || $this->getServer("https")) ? 'https://' : 'http://';
    }

    /**
     * Devuelve la url completa de base
     * @param boolean $protocol
     * @return string
    */
    public function getRootUrl($protocol = true)
    {
        $host = $this->getServerName();
        $protocol = $protocol ? $this->getProtocol() : '';
        $url = '';
        if(!empty($host) && !empty($protocol)) $url = $protocol . $host;
        return $url;
    }

    /**
     * Método que devuelve el valor de una cookie en caso de que exista
     * @param $name
     *
     * @return null
     */
    public function getCookie($name)
    {
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * Método que devuelve los files subidos por POST
     * @param $name
     *
     * @return array
     */
    public function getFile($name)
    {
        return array_key_exists($name, $this->upload) ? $this->upload[$name] : array();
    }

    /**
     * Método que devuelve si la petición es ajax o no
     * @return bool
     */
    public function isAjax()
    {
        $requested =$this->getServer("HTTP_X_REQUESTED_WITH");
        return (!empty($requested) && strtolower($requested) == 'xmlhttprequest');
    }

}
