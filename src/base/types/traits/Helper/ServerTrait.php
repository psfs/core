<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\config\Config;

/**
 * Trait ServerTrait
 * @package PSFS\base\types\traits\Helper
 */
trait ServerTrait
{
    /**
     * @var array
     */
    private $server = [];

    /**
     * @param string $key
     * @param null|string $default
     * @return null|string
     */
    public function getServer($key, $default = null)
    {
        $value = null;
        if (array_key_exists($key, $this->server)) {
            $value = $this->server[$key];
        }
        return $value ?: $default;
    }

    /**
     * @param array $server
     * @return ServerTrait
     */
    public function setServer(array $server)
    {
        $this->server = $server;
        return $this;
    }

    /**
     * @param bool $formatted
     * @return false|string|null
     */
    public function getTs($formatted = false)
    {
        return $formatted ? date('Y-m-d H:i:s', $this->getServer('REQUEST_TIME_FLOAT')) : $this->getServer('REQUEST_TIME_FLOAT');
    }

    /**
     * @return string
     */
    public function getServerName()
    {
        $serverName = $this->getServer('HTTP_HOST', $this->getServer('SERVER_NAME', 'localhost'));
        return explode(':', $serverName)[0];
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        if (Config::getParam('force.https', false)) {
            return 'https://';
        }
        return ($this->getServer('HTTPS') || $this->getServer('https')) ? 'https://' : 'http://';
    }

    /**
     * @return boolean
     */
    public function isAjax()
    {
        $requested = $this->getServer('HTTP_X_REQUESTED_WITH');
        return (null !== $requested && strtolower($requested) === 'xmlhttprequest');
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return strtoupper($this->getServer('REQUEST_METHOD', 'GET'));
    }

    /**
     * @return string
     */
    public function getRequestUri()
    {
        return $this->getServer('REQUEST_URI', '');
    }

    /**
     * Método que devuelve el idioma de la petición
     * @return string
     */
    public function getLanguage()
    {
        return $this->getServer('HTTP_ACCEPT_LANGUAGE', Config::getParam('default.language', 'es_ES'));
    }

}
