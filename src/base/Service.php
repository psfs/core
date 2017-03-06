<?php

namespace PSFS\base;
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
     * @Inyectable
     * @var \PSFS\base\Logger Log de las llamadas
     */
    protected $log;
    /**
     * @Inyectable
     * @var \PSFS\base\Cache $cache
     */
    protected $cache;

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
        curl_setopt_array($this->con, $this->options);
    }

    protected function setDefaults()
    {
        switch (strtoupper($this->type)) {
            case 'GET':
            default:
                $this->addOption(CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case 'POST':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "POST");
                $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                break;
            case 'DELETE':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            case 'PUT':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "PUT");
                $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                break;
            case 'PATCH':
                $this->addOption(CURLOPT_CUSTOMREQUEST, "PATCH");
                $this->addOption(CURLOPT_POSTFIELDS, json_encode($this->params));
                break;
        }

        $this->addOption(CURLOPT_RETURNTRANSFER, true);
        $this->addOption(CURLOPT_FOLLOWLOCATION, true);
    }

    public function callSrv()
    {
        $this->setDefaults();
        $this->applyOptions();
        $result = curl_exec($this->con);
        $this->result = json_decode($result, true);
        $this->info = curl_getinfo($this->con);
    }

    /**
     * @return mixed
     */
    public function getCallInfo() {
        return $this->info;
    }
}
