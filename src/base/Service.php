<?php
namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\SecurityHelper;

/**
 * Class Service
 * @package PSFS\base
 */
class Service extends Singleton
{
    /**
     * @var String Url de destino de la llamada
     */
    private $url;
    /**
     * @var array Parámetros de la llamada
     */
    private $params;
    /**
     * @var array Opciones llamada
     */
    private $options;
    /**
     * @var array Cabeceras de la llamada
     */
    private $headers;
    /**
     * @var string type
     */
    private $type;
    /**
     * @var resource $con
     */
    private $con;
    /**
     * @var string $result
     */
    private $result;
    /**
     * @var mixed
     */
    private $info;

    /**
     * @Injectable
     * @var \PSFS\base\Logger Log de las llamadas
     */
    protected $log;
    /**
     * @Injectable
     * @var \PSFS\base\Cache $cache
     */
    protected $cache;
    /**
     * @var bool
     */
    protected $isJson = true;

    private function closeConnection() {
        if(null !== $this->con) {
            curl_close($this->con);
        }
    }

    public function __destruct()
    {
        $this->closeConnection();
    }

    /**
     * @return String
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param String $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        $this->initialize();
    }

    /**
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param string $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Add request param
     *
     * @param $key
     * @param null $value
     *
     * @return \PSFS\base\Service
     */
    public function addParam($key, $value = NULL)
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Add request param
     *
     * @param $key
     * @param null $value
     *
     * @return \PSFS\base\Service
     */
    public function addOption($key, $value = NULL)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @param $header
     * @param null $content
     *
     * @return $this
     */
    public function addHeader($header, $content = NULL)
    {
        $this->headers[$header] = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param Logger $log
     */
    public function setLog($log)
    {
        $this->log = $log;
    }

    /**
     * @param bool $isJson
     */
    public function setIsJson($isJson = true) {
        $this->isJson = $isJson;
    }

    /**
     * @return bool
     */
    public function getIsJson() {
        return $this->isJson;
    }

    /**
     * Método que limpia el contexto de la llamada
     */
    private function clearContext()
    {
        $this->url = NULL;
        $this->params = array();
        $this->headers = array();
        Logger::log("Context service for " . get_called_class() . " cleared!");
        $this->closeConnection();
    }

    /**
     *
     */
    public function init()
    {
        parent::init();
        $this->clearContext();
    }

    /**
     * Initialize CURL
     */
    private function initialize()
    {
        $this->closeConnection();
        $this->con = curl_init($this->url);
    }

    /**
     * Generate auth header
     * @param string $secret
     * @param string $module
     */
    protected function addRequestToken($secret, $module = 'PSFS')
    {
        $this->addHeader('X-PSFS-SEC-TOKEN', SecurityHelper::generateToken($secret, $module));
    }

    /**
     * @param $user
     * @param $pass
     */
    protected function addAuthHeader($user, $pass) {
        $this->addOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->addOption(CURLOPT_USERPWD, "$user:$pass");
    }

    protected function applyOptions() {
        if(count($this->options)) {
            curl_setopt_array($this->con, $this->options);
        }
    }

    protected function applyHeaders() {
        $headers = [];
        foreach($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        if(count($headers)) {
            curl_setopt($this->con, CURLOPT_HTTPHEADER, $headers);
        }
    }

    protected function setDefaults()
    {
        switch (strtoupper($this->type)) {
            case 'GET':
            default:
                $this->addOption(CURLOPT_CUSTOMREQUEST, "GET");
                if(!empty($this->params)) {
                    $sep = false === preg_match('/\?/', $this->getUrl()) ? '?' : '';
                    $this->setUrl($this->getUrl() . $sep . http_build_query($this->params));
                }
                break;
            case 'POST':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "POST");
                if($this->getIsJson()) {
                    $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                } else {
                    $this->addOption(CURLOPT_POSTFIELDS, http_build_query($this->params));
                }
                break;
            case 'DELETE':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            case 'PUT':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "PUT");

                if($this->getIsJson()) {
                    $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                } else {
                    $this->addOption(CURLOPT_POSTFIELDS, http_build_query($this->params));
                }
                break;
            case 'PATCH':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "PATCH");
                if($this->getIsJson()) {
                    $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                } else {
                    $this->addOption(CURLOPT_POSTFIELDS, http_build_query($this->params));
                }
                break;
        }

        $this->addOption(CURLOPT_RETURNTRANSFER, true);
        $this->addOption(CURLOPT_FOLLOWLOCATION, true);
        $this->addOption(CURLOPT_SSL_VERIFYHOST, false);
        $this->addOption(CURLOPT_SSL_VERIFYPEER, false);
    }

    public function callSrv()
    {
        $this->setDefaults();
        $this->applyOptions();
        $this->applyHeaders();
        if('debug' === Config::getParam('log.level')) {
            curl_setopt($this->con, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($this->con, CURLOPT_STDERR, $verbose);
        }
        $result = curl_exec($this->con);
        $this->result = $this->isJson ? json_decode($result, true) : $result;
        if('debug' === Config::getParam('log.level')) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            Logger::log($verboseLog, LOG_DEBUG, [
                'headers' => $this->getHeaders(),
                'options' => $this->getOptions(),
                'url' => $this->getUrl(),
            ]);
        }
        Logger::log($this->url . ' response: ', LOG_DEBUG, $this->result);
        $this->info = curl_getinfo($this->con);
    }

    /**
     * @return mixed
     */
    public function getCallInfo() {
        return $this->info;
    }

}
