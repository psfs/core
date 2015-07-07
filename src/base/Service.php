<?php

    namespace PSFS\base;

    /**
     * Class Service
     * @package PSFS\base
     */
    class Service extends Singleton{
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
         * @Inyectable
         * @var \PSFS\base\Logger Log de las llamadas
         */
        protected $log;

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
         * @param $key
         * @param null $value
         * @return \PSFS\base\Service
         */
        public function addParam($key, $value = null) {
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
         * @return $this
         */
        public function addHeader($header, $content = null) {
            $this->headers[$header] = $content;
            return $this;
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
        private function clearContext() {
            $this->url = null;
            $this->params = array();
            $this->headers = array();
            $this->log->debugLog(_("Context service cleared!"));
        }

        /**
         * Constructor por defecto
         */
        public function __construct() {
            $this->init();
            $this->clearContext();
        }
    }