<?php
namespace PSFS\base\types\traits;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\ResponseHelper;

/**
 * Class OutputTrait
 * @package PSFS\base\types\traits
 */
trait OutputTrait {

    use BoostrapTrait;
    /**
     * @var bool
     */
    protected $public_zone = true;
    /**
     * @var string
     */
    private $status_code = Template::STATUS_OK;
    /**
     * @var bool
     */
    protected $debug = true;

    public function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: FALSE;
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
                $this->status_code = "HTTP/1.0 500 Internal Server Error";
                break;
            case '404':
                $this->status_code = "HTTP/1.0 404 Not Found";
                break;
            case '403':
                $this->status_code = "HTTP/1.0 403 Forbidden";
                break;
            case '402':
                $this->status_code = "HTTP/1.0 402 Payment Required";
                break;
            case '401':
                $this->status_code = "HTTP/1.0 401 Unauthorized";
                break;
            case '400':
                $this->status_code = "HTTP/1.0 400 Bad Request";
                break;
        }
        return $this;
    }

    /**
     * Servicio que establece las cabeceras de la respuesta
     * @param string $contentType
     * @param array $cookies
     */
    private function setReponseHeaders($contentType = 'text/html', array $cookies = array())
    {
        $config = Config::getInstance();
        $powered = $config->get("poweredBy");
        if (empty($powered)) {
            $powered = "@c15k0";
        }
        header("X-Powered-By: $powered");
        ResponseHelper::setStatusHeader($this->status_code);
        ResponseHelper::setAuthHeaders($this->public_zone);
        ResponseHelper::setCookieHeaders($cookies);
        header('Content-type: ' . $contentType);

    }

    /**
     * Servicio que devuelve el output
     * @param string $output
     * @param string $contentType
     * @param array $cookies
     * @return string HTML
     */
    public function output($output = '', $contentType = 'text/html', array $cookies = array())
    {
        Logger::log('Start output response');
        ob_start();
        $this->setReponseHeaders($contentType, $cookies);
        header('Content-length: ' . strlen($output));

        $needCache = Cache::needCache();
        if (false !== $needCache && $this->status_code === Template::STATUS_OK && $this->debug === false) {
            $cache = Cache::getInstance();
            Logger::log('Saving output response into cache');
            $cacheName = $cache->getRequestCacheHash();
            $tmpDir = substr($cacheName, 0, 2) . DIRECTORY_SEPARATOR . substr($cacheName, 2, 2) . DIRECTORY_SEPARATOR;
            $cache->storeData("json" . DIRECTORY_SEPARATOR . $tmpDir . $cacheName, $output);
            $cache->storeData("json" . DIRECTORY_SEPARATOR . $tmpDir . $cacheName . ".headers", headers_list(), Cache::JSON);
        }
        echo $output;

        ob_flush();
        ob_end_clean();
        Logger::log('End output response');
        $this->closeRender();
    }

    /**
     * Método que cierra y limpia los buffers de salida
     */
    public function closeRender()
    {
        Logger::log('Close template render');
        $uri = Request::requestUri();
        Security::getInstance()->setSessionKey("lastRequest", array(
            "url" => Request::getInstance()->getRootUrl() . $uri,
            "ts" => microtime(true),
        ));
        Security::getInstance()->updateSession();
        Logger::log('End request: ' . $uri, LOG_INFO);
        exit;
    }

    /**
     * Método que devuelve los datos cacheados con las cabeceras que tenía por entonces
     * @param string $data
     * @param array $headers
     */
    public function renderCache($data, $headers = array())
    {
        ob_start();
        for ($i = 0, $ct = count($headers); $i < $ct; $i++) {
            header($headers[$i]);
        }
        header('X-PSFS-CACHED: true');
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
     * Método que devuelve una respuesta con formato
     * @param string $response
     * @param string $type
     */
    public function response($response, $type = 'text/html')
    {
        $this->output($response, $type);
    }

}