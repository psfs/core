<?php

namespace PSFS\base;

use PSFS\base\exception\RequestTerminationException;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\helpers\RequestPayloadHelper;
use PSFS\base\types\helpers\ServerHelper;
use PSFS\base\types\traits\Helper\ServerTrait;
use PSFS\base\types\traits\SingletonTrait;

/**
 * @package PSFS
 */
class Request
{
    use SingletonTrait;
    use ServerTrait;

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
    protected array $cookies;
    /**
     * @var array
     */
    protected array $upload;
    /**
     * @var array
     */
    protected array $header;
    /**
     * @var array
     */
    protected array $data;
    /**
     * @var array
     */
    protected array $raw = [];
    /**
     * @var array
     */
    protected array $query;
    /**
     * @var bool
     */
    private bool $isLoaded = false;

    public function init()
    {
        $this->setServer(ServerHelper::getServerData());
        $this->header = RequestPayloadHelper::parseHeaders($this->server);

        $bags = RequestPayloadHelper::hydratePayloadBags($_COOKIE, $_FILES, $_REQUEST, $_GET);
        $this->cookies = $bags['cookies'];
        $this->upload = $bags['upload'];
        $this->data = $bags['data'];
        $this->query = $bags['query'];

        $this->raw = RequestPayloadHelper::decodeRawBody(RequestPayloadHelper::extractRawBody($this->server));
        $this->isLoaded = true;
    }

    /**
     * @param $header
     *
     * @return boolean
     */
    public function hasHeader($header): bool
    {
        return array_key_exists($header, $this->header);
    }


    /**
     * @return boolean
     */
    public function hasCookies(): bool
    {
        return (null !== $this->cookies && 0 !== count($this->cookies));
    }

    /**
     * @return boolean
     */
    public function hasUpload(): bool
    {
        return (null !== $this->upload && 0 !== count($this->upload));
    }

    /**
     *
     * @param boolean $formatted
     *
     * @return string
     */
    public static function getTimestamp(?bool $formatted = false)
    {
        return self::getInstance()->getTs($formatted);
    }

    /**
     * @param string $name
     * @param string|null $default
     *
     * @return string|null
     */
    public static function header(string $name, ?string $default = null): ?string
    {
        return self::getInstance()->getHeader($name, $default);
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        $header = null;
        if ($this->hasHeader($name)) {
            $header = $this->header[$name];
        } else {
            if (array_key_exists('h_' . strtolower($name), $this->query)) {
                $header = $this->query['h_' . strtolower($name)];
            } else {
                if (array_key_exists('HTTP_' . strtoupper(str_replace('-', '_', $name)), $this->server)) {
                    $header = $this->getServer('HTTP_' . strtoupper(str_replace('-', '_', $name)));
                }
            }
        }
        return $header ?: $default;
    }

    /**
     * @param string $language
     * @return void
     */
    public static function setLanguageHeader(string $language): void
    {
        self::getInstance()->header['X-API-LANG'] = $language;
    }

    /**
     * @return string|null
     */
    public static function requestUri(): ?string
    {
        return self::getInstance()->getRequestUri();
    }

    /**
     * @return boolean
     */
    public function isFile(): bool
    {
        return preg_match('/\.[a-z0-9]{2,4}$/', $this->getRequestUri()) !== 0;
    }

    /**
     *
     * @param string $queryParams
     *
     * @return mixed
     */
    public function getQuery(string $queryParams): mixed
    {
        return array_key_exists($queryParams, $this->query) ? $this->query[$queryParams] : null;
    }

    /**
     *
     * @return mixed
     */
    public function getQueryParams(): mixed
    {
        return $this->query;
    }

    /**
     * @param string $param
     *
     * @return string|null
     */
    public function get(string $param): ?string
    {
        return array_key_exists($param, $this->data) ? $this->data[$param] : null;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return array_merge($this->data, $this->raw);
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->raw;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getCookie(string $name): ?string
    {
        return array_key_exists($name, $this->cookies) ? $this->cookies[$name] : null;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getFile(string $name): array
    {
        return array_key_exists($name, $this->upload) ? $this->upload[$name] : [];
    }

    /**
     * @param string|null $url
     */
    public function redirect(?string $url = null)
    {
        if (null === $url) {
            $url = $this->getServer('HTTP_ORIGIN');
        }
        ob_start();
        header('Location: ' . $url);
        ob_end_clean();
        Security::getInstance()->updateSession();
        if (RuntimeMode::isLongRunningServer()) {
            throw new RequestTerminationException((string)t('Redirect...'));
        }
        exit(t('Redirect...'));
    }

    /**
     * @param boolean $hasProtocol
     * @return string
     */
    public function getRootUrl(?bool $hasProtocol = true): string
    {
        $url = $this->getServerName();
        $protocol = $hasProtocol ? $this->getProtocol() : '//';
        if (!empty($protocol)) {
            $url = $protocol . $url;
        }
        return $this->checkServerPort($url);
    }

    /**
     * @param string $url
     * @return string
     */
    protected function checkServerPort(string $url): string
    {
        $port = (integer)$this->getServer('SERVER_PORT');
        $host = $this->getServer('HTTP_HOST');
        if (!empty($host)) {
            $parts = explode(':', $host);
            $hostPort = (integer)end($parts);
            if ($hostPort !== $port && count($parts) > 1) {
                $port = $hostPort;
            }
        }
        if (!in_array($port, [80, 443], true)) {
            $url .= ':' . $port;
        }
        return $url;
    }

}
