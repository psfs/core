<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\config\Config;

/**
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
        $timestamp = $this->getServer('REQUEST_TIME_FLOAT');
        $timestamp = is_numeric($timestamp) ? (int)$timestamp : null;
        return $formatted ? date('Y-m-d H:i:s', $timestamp) : $timestamp;
    }

    /**
     * @return string
     */
    public function getServerName()
    {
        $serverName = $this->extractForwardedHost();
        if (empty($serverName)) {
            $serverName = $this->extractHostFromValue((string)$this->getServer('HTTP_HOST', ''));
        }
        if (empty($serverName)) {
            $serverName = $this->extractHostFromValue((string)$this->getServer('SERVER_NAME', ''));
        }

        return true === in_array(
            strtolower($serverName),
            ['0.0.0.0', '127.0.0.1', '::1', 'docker.host.internal'],
            true
        ) ? 'localhost' : $serverName;
    }

    /**
     * @return string
     */
    private function extractForwardedHost(): string
    {
        $forwarded = trim((string)$this->getServer('HTTP_FORWARDED', ''));
        if ($forwarded !== '') {
            $entry = trim((string)explode(',', $forwarded)[0]);
            if (preg_match('/(?:^|;)\\s*host\\s*=\\s*\"?([^\";]+)\"?/i', $entry, $matches) === 1) {
                return $this->extractHostFromValue($matches[1]);
            }
        }

        foreach (['HTTP_X_FORWARDED_HOST', 'HTTP_X_ORIGINAL_HOST', 'HTTP_X_HOST', 'HTTP_X_FORWARDED_SERVER'] as $header) {
            $candidate = trim((string)$this->getServer($header, ''));
            if ($candidate === '') {
                continue;
            }
            $candidate = trim((string)explode(',', $candidate)[0]);
            $host = $this->extractHostFromValue($candidate);
            if ($host !== '') {
                return $host;
            }
        }

        return '';
    }

    /**
     * @param string $value
     * @return string
     */
    private function extractHostFromValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $host = (string)parse_url($value, PHP_URL_HOST);
        if ($host === '') {
            $host = (string)parse_url('http://' . $value, PHP_URL_HOST);
        }
        if ($host !== '') {
            return $host;
        }

        if (preg_match('/^\\[([0-9a-f:.]+)](?::\\d+)?$/i', $value, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('/^([^:]+):\\d+$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
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
        return strtoupper((string)$this->getServer('REQUEST_METHOD', 'GET'));
    }

    /**
     * @return string
     */
    public function getRequestUri()
    {
        return $this->getServer('REQUEST_URI', '');
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->getServer('HTTP_ACCEPT_LANGUAGE', Config::getParam('default.language', 'en_US'));
    }

}
