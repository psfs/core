<?php
namespace PSFS\base;

use PSFS\base\types\traits\SingletonTrait;

/**
 * Class Request
 * @package PSFS
 */
class Request
{
    use SingletonTrait;

    const VERB_GET = 'GET';
    const VERB_POST = 'POST';
    const VERB_PUT = 'PUT';
    const VERB_DELETE = 'DELETE';
    const VERB_OPTIONS = 'OPTIONS';
    const VERB_HEAD = 'HEAD';
    const VERB_PATCH = 'PATCH';

    /**
     * @var array
     */
    protected $server;
    /**
     * @var array
     */
    protected $cookies;
    /**
     * @var array
     */
    protected $upload;
    /**
     * @var array
     */
    protected $header;
    /**
     * @var array
     */
    protected $data;
    /**
     * @var array
     */
    protected $raw = [];
    /**
     * @var array
     */
    protected $query;
    /**
     * @var bool
     */
    private $isLoaded = false;

    public function init()
    {
        $this->server = $_SERVER or [];
        $this->cookies = $_COOKIE or [];
        $this->upload = $_FILES or [];
        $this->header = $this->parseHeaders();
        $this->data = $_REQUEST or [];
        $this->query = $_GET or [];
        $this->raw = json_decode(file_get_contents('php://input'), true) ?: [];
        $this->isLoaded = true;
    }

    /**
     * Método que devuelve las cabeceras de la petición
     * @return array
     */
    private function parseHeaders()
    {
        return getallheaders();
    }

    /**
     * Método que verifica si existe una cabecera concreta
     * @param $header
     *
     * @return boolean
     */
    public function hasHeader($header)
    {
        return array_key_exists($header, $this->header);
    }


    /**
     * Método que indica si una petición tiene cookies
     * @return boolean
     */
    public function hasCookies()
    {
        return (null !== $this->cookies && 0 !== count($this->cookies));
    }

    /**
     * Método que indica si una petición tiene cookies
     * @return boolean
     */
    public function hasUpload()
    {
        return (null !== $this->upload && 0 !== count($this->upload));
    }

    /**
     * Método que devuelve el TimeStamp de la petición
     *
     * @param boolean $formatted
     *
     * @return string
     */
    public static function getTimestamp($formatted = false)
    {
        return self::getInstance()->getTs($formatted);
    }

    public function getTs($formatted = false)
    {
        return $formatted ? date('Y-m-d H:i:s', $this->server['REQUEST_TIME_FLOAT']) : $this->server['REQUEST_TIME_FLOAT'];
    }

    /**
     * Método que devuelve el Método HTTP utilizado
     * @return string
     */
    public function getMethod()
    {
        return array_key_exists('REQUEST_METHOD', $this->server) ? strtoupper($this->server['REQUEST_METHOD']) : 'GET';
    }

    /**
     * Método que devuelve una cabecera de la petición si existe
     * @param string $name
     * @param string $default
     *
     * @return string|null
     */
    public static function header($name, $default = null)
    {
        return self::getInstance()->getHeader($name,  $default);
    }

    /**
     * @param string $name
     * @param string $default
     * @return string|null
     */
    public function getHeader($name, $default = null)
    {
        $header = null;
        if ($this->hasHeader($name)) {
            $header = $this->header[$name];
        } else if(array_key_exists('h_' . strtolower($name), $this->query)) {
            $header = $this->query['h_' . strtolower($name)];
        } else if(array_key_exists('HTTP_' . strtoupper(str_replace('-', '_', $name)), $this->server)) {
            $header = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))];
        }
        return $header ?: $default;
    }

    /**
     * Método que devuelve la url solicitada
     * @return string|null
     */
    public static function requestUri()
    {
        return self::getInstance()->getRequestUri();
    }

    /**
     * @return string
     */
    public function getRequestUri()
    {
        return array_key_exists('REQUEST_URI', $this->server) ? $this->server['REQUEST_URI'] : '';
    }

    /**
     * Método que devuelve el idioma de la petición
     * @return string
     */
    public function getLanguage()
    {
        return array_key_exists('HTTP_ACCEPT_LANGUAGE', $this->server) ? $this->server['HTTP_ACCEPT_LANGUAGE'] : 'es_ES';
    }

    /**
     * Método que determina si se ha solicitado un fichero
     * @return boolean
     */
    public function isFile()
    {
        return preg_match('/\.[a-z0-9]{2,4}$/', $this->getRequestUri()) !== 0;
    }

    /**
     * Get query params
     *
     * @param string $queryParams
     *
     * @return mixed
     */
    public function getQuery($queryParams)
    {
        return array_key_exists($queryParams, $this->query) ? $this->query[$queryParams] : null;
    }

    /**
     * Get all query params
     *
     * @return mixed
     */
    public function getQueryParams()
    {
        return $this->query;
    }

    /**
     * Método que devuelve un parámetro de la solicitud
     * @param string $param
     *
     * @return string|null
     */
    public function get($param)
    {
        return array_key_exists($param, $this->data) ? $this->data[$param] : null;
    }

    /**
     * Método que devuelve todos los datos del Request
     * @return array
     */
    public function getData()
    {
        return array_merge($this->data, $this->raw);
    }

    /**
     * @return array
     */
    public function getRawData() {
        return $this->raw;
    }

    /**
     * Método que realiza una redirección a la url dada
     * @param string $url
     */
    public function redirect($url = null)
    {
        if (null === $url) {
            $url = $this->getServer('HTTP_ORIGIN');
        }
        ob_start();
        header('Location: ' . $url);
        ob_end_clean();
        Security::getInstance()->updateSession();
        exit(t('Redireccionando...'));
    }

    /**
     * Devuelve un parámetro de $_SERVER
     * @param string $param
     * @param string|null $default
     * @return string|null
     */
    public function getServer($param, $default = null)
    {
        return array_key_exists($param, $this->server) ? $this->server[$param] : $default;
    }

    /**
     * Devuelve el nombre del servidor
     * @return string
     */
    public function getServerName()
    {
        $serverName = $this->getServer('SERVER_NAME', 'localhost');
        return strlen($serverName) ? $serverName : $this->getServer('HTTP_HOST', 'localhost');
    }

    /**
     * Devuelve el protocolo de la conexión
     * @return string
     */
    public function getProtocol()
    {
        return ($this->getServer('HTTPS') || $this->getServer('https')) ? 'https://' : 'http://';
    }

    /**
     * Devuelve la url completa de base
     * @param boolean $hasProtocol
     * @return string
     */
    public function getRootUrl($hasProtocol = true)
    {
        $url = $this->getServerName();
        $protocol = $hasProtocol ? $this->getProtocol() : '';
        if (!empty($protocol)) {
            $url = $protocol . $url;
        }
        $url = $this->checkServerPort($url);
        return $url;
    }

    /**
     * Método que devuelve el valor de una cookie en caso de que exista
     * @param string $name
     *
     * @return string
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
     * @return boolean
     */
    public function isAjax()
    {
        $requested = $this->getServer('HTTP_X_REQUESTED_WITH');
        return (null !== $requested && strtolower($requested) === 'xmlhttprequest');
    }

    /**
     * @param string $url
     * @return string
     */
    protected function checkServerPort(string $url): string
    {
        $port = (integer)$this->getServer('SERVER_PORT');
        $host = $this->getServer('HTTP_HOST');
        if(!empty($host)) {
            $parts = explode(':', $host);
            $hostPort = (integer)end($parts);
            if(count($parts) > 1 && $hostPort !== $port) {
                $port = $hostPort;
            }
        }
        if (!in_array($port, [80, 443], true)) {
            $url .= ':' . $port;
        }
        return $url;
    }

}
