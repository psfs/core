<?php

namespace PSFS\base;

use PSFS\base\Singleton;

/**
 * Class Request
 * @package PSFS
 */
class Request extends Singleton{
    protected $server;
    protected $cookies;
    protected $upload;
    protected $header;

    public function __construct(){
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->upload = $_FILES;
        $this->header = $this->parseHeaders();
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

    public function getLanguage()
    {

    }
}