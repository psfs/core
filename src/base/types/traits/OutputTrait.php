<?php

namespace PSFS\base\types\traits;

use JetBrains\PhpStorm\NoReturn;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\EventHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;

/**
 * Class OutputTrait
 * @package PSFS\base\types\traits
 */
trait OutputTrait
{
    use BoostrapTrait;
    use TestTrait;

    /**
     * @var bool
     */
    protected $public_zone = true;
    /**
     * @var string
     */
    protected $status_code = Template::STATUS_OK;
    /**
     * @var bool
     */
    protected $debug = true;

    public function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: false;
    }

    /**
     * @return bool
     */
    public function isPublicZone()
    {
        return $this->public_zone;
    }

    /**
     * @param bool $publicZone
     * @return OutputTrait
     */
    public function setPublicZone($publicZone)
    {
        $this->public_zone = $publicZone;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatusCode()
    {
        return $this->status_code;
    }

    /**
     * @param string $statusCode
     * @return OutputTrait
     */
    public function setStatusCode($statusCode)
    {
        $this->status_code = $statusCode;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     * @return OutputTrait
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Método que establece un header de http status code
     * @param string $status
     *
     * @return $this
     */
    public function setStatus($status = null)
    {
        switch ($status) {
            //TODO implement all status codes
            case '500':
                $this->setStatusCode('HTTP/1.0 500 Internal Server Error');
                break;
            case '404':
                $this->setStatusCode('HTTP/1.0 404 Not Found');
                break;
            case '403':
                $this->setStatusCode('HTTP/1.0 403 Forbidden');
                break;
            case '402':
                $this->setStatusCode('HTTP/1.0 402 Payment Required');
                break;
            case '401':
                $this->setStatusCode('HTTP/1.0 401 Unauthorized');
                break;
            case '400':
                $this->setStatusCode('HTTP/1.0 400 Bad Request');
                break;
            case '200':
                $this->setStatusCode(Template::STATUS_OK);
                break;
            default:
                $this->setStatusCode('HTTP/1.0 ' . ($status ?: 200));
                break;
        }
        return $this;
    }

    /**
     * Servicio que establece las cabeceras de la respuesta
     * @param string $contentType
     * @param array $cookies
     */
    private function setResponseHeaders($contentType = 'text/html', array $cookies = array()): void
    {
        ResponseHelper::setHeader('X-Powered-By: ' . Config::getParam('poweredBy', 'PSFS'));
        ResponseHelper::setStatusHeader($this->getStatusCode());
        ResponseHelper::setAuthHeaders($this->isPublicZone());
        ResponseHelper::setCookieHeaders($cookies);
        ResponseHelper::setHeader('Content-type: ' . $contentType);

    }

    /**
     * Servicio que devuelve el output
     * @param string $output
     * @param string $contentType
     * @param array $cookies
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function output($output = '', $contentType = 'text/html', array $cookies = array())
    {
        if (!self::isTest()) {
            Logger::log('Start output response');
            ob_start();
            $this->setResponseHeaders($contentType, $cookies);
            ResponseHelper::setHeader('Content-length: ' . strlen($output));
            ResponseHelper::setHeader('CRC: ' . crc32($output));

            $needCache = Cache::needCache();
            $cache = Cache::getInstance();
            list($path, $cacheDataName) = $cache->getRequestCacheHash();
            if (null !== $cacheDataName && false !== $needCache && $this->getStatusCode() === Template::STATUS_OK) {
                Logger::log('Saving output response into cache');
                $cache->storeData('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName, $output);
                $cache->storeData('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName . '.headers', headers_list(), Cache::JSON);
            } elseif (Request::getInstance()->getMethod() !== 'GET') {
                $cache->flushCache();
            }
            echo $output;

            ob_flush();
            ob_end_clean();
            Logger::log('End output response');
            $this->closeRender();
        } else {
            return $output;
        }
    }

    /**
     * Método que cierra y limpia los buffers de salida
     */
    #[NoReturn] public function closeRender(): void
    {
        Logger::log('Close template render');
        $uri = Request::requestUri();
        Security::getInstance()->setSessionKey('lastRequest', array(
            'url' => Request::getInstance()->getRootUrl() . $uri,
            'ts' => microtime(true),
        ));
        Security::getInstance()->updateSession();
        EventHelper::handleEvents(EventHelper::EVENT_END_REQUEST);
        Logger::log('End request: ' . Request::requestUri(), LOG_INFO);
        exit;
    }

    /**
     * Método que devuelve los datos cacheados con las cabeceras que tenía por entonces
     * @param string $data
     * @param array $headers
     */
    public function renderCache($data, $headers = array()): void
    {
        Inspector::stats('[OutputTrait] Rendering cache', Inspector::SCOPE_DEBUG);
        ob_start();
        for ($i = 0, $ct = count($headers); $i < $ct; $i++) {
            ResponseHelper::setHeader($headers[$i]);
        }
        ResponseHelper::setHeader('X-PSFS-CACHED: true');
        echo $data;
        ob_flush();
        ob_end_clean();
        $this->closeRender();
    }

    /**
     * Método que fuerza la descarga de un fichero
     * @param $data
     * @param string $content
     * @param string $filename
     */
    public function download($data, $content = 'text/html', $filename = 'data.txt'): void
    {
        ob_start();
        ResponseHelper::setHeader('Pragma: public');
        /////////////////////////////////////////////////////////////
        // prevent caching....
        /////////////////////////////////////////////////////////////
        // Date in the past sets the value to already have been expired.
        ResponseHelper::setHeader('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        ResponseHelper::setHeader('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        ResponseHelper::setHeader('Cache-Control: no-store, no-cache, must-revalidate'); // HTTP/1.1
        ResponseHelper::setHeader('Cache-Control: pre-check=0, post-check=0, max-age=0'); // HTTP/1.1
        ResponseHelper::setHeader('Pragma: no-cache');
        ResponseHelper::setHeader('Expires: 0');
        ResponseHelper::setHeader('Content-Transfer-Encoding: binary');
        ResponseHelper::setHeader('Content-type: ' . $content);
        ResponseHelper::setHeader('Content-length: ' . strlen($data));
        ResponseHelper::setHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        ResponseHelper::setHeader('Access-Control-Expose-Headers: Filename');
        ResponseHelper::setHeader('Filename: ' . $filename);
        echo $data;
        ob_flush();
        ob_end_clean();
        $this->closeRender();
    }

    /**
     * Método que devuelve una respuesta con formato
     * @param string $response
     * @param string $type
     * @throws GeneratorException
     */
    public function response($response, $type = 'text/html'): void
    {
        $this->output($response, $type);
    }

}
