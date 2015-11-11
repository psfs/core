<?php

    namespace PSFS\base;

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
         * @var Array Parámetros de la llamada
         */
        private $params;
        /**
         * @var Array Cabeceras de la llamada
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
         * @Inyectable
         * @var \PSFS\base\Logger Log de las llamadas
         */
        protected $log;
        /**
         * @Inyectable
         * @var \PSFS\base\Cache $cache
         */
        protected $cache;

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
         * @return Array
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
         * @param Array $params
         */
        public function setParams($params)
        {
            $this->params = $params;
        }

        /**
         * @return Array
         */
        public function getHeaders()
        {
            return $this->headers;
        }

        /**
         * @param Array $headers
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
            $this->log->debugLog(_("Context service for " . get_called_class() . " cleared!"));
        }

        /**
         * Constructor por defecto
         */
        public function __construct()
        {
            parent::__construct();
            $this->clearContext();
        }

        /**
         * Initialize CURL
         */
        private function initialize()
        {
            $this->con = curl_init($this->url);
        }

        /**
         * Generate auth header
         * @param string $secret
         */
        protected function addRequestToken($secret)
        {
            $this->addHeader('X-PSFS-SEC-TOKEN', Security::generateToken($secret));
        }

        protected function setOpts()
        {
            switch(strtoupper($this->type)) {
                case 'GET':
                default:
                    curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, "GET");
                    break;
                case 'POST':
                    curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($this->con, CURLOPT_POSTFIELDS, json_encode($this->params));
                    break;
                case 'DELETE':
                    curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, "DELETE");
                    break;
                case 'PUT':
                    curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($this->con, CURLOPT_POSTFIELDS, json_encode($this->params));
                    break;
            }

            curl_setopt($this->con, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->con, CURLOPT_FOLLOWLOCATION, true);
        }

        public function callSrv()
        {
            $this->setOpts();
            $result = curl_exec($this->con);
            $this->result = json_decode($result);
        }
    }
