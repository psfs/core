<?php

    namespace PSFS\base;

    use PSFS\base\Singleton;
    use Monolog\Logger as Monolog;
    use Monolog\Handler\FirePHPHandler;
    use Monolog\Handler\StreamHandler;
    use Monolog\Processor\WebProcessor;
    use Monolog\Processor\MemoryUsageProcessor;

    if(!defined("LOG_DIR"))
    {
        if(!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . 'logs')) @mkdir(BASE_DIR . DIRECTORY_SEPARATOR . 'logs', 0755, true);
        define("LOG_DIR", BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
    }

    class Logger extends Singleton{

        protected $logger;
        private $stream;

        /**
         * @param string $path
         * @return $this
         */
        public function __construct()
        {
            $args = func_get_args();
            $logger = 'general';
            $debug = false;
            if(!empty($args))
            {
                if(isset($args[0][0])) $logger = $args[0][0];
                if(isset($args[0][1])) $debug = $args[0][1];
            }
            $path = LOG_DIR . DIRECTORY_SEPARATOR . strtoupper($logger) . DIRECTORY_SEPARATOR . date('Y'). DIRECTORY_SEPARATOR . date('m');
            if(!file_exists($path)) @mkdir($path, 0755, true);
            $this->stream = fopen($path . DIRECTORY_SEPARATOR . date("Ymd") . ".log", "a+");
            $this->logger = new Monolog(strtoupper($logger));
            $this->logger->pushHandler(new StreamHandler($this->stream));
            if($debug)
            {
                $this->logger->pushHandler(new FirePHPHandler());
                $this->logger->pushProcessor(new MemoryUsageProcessor());
            }
            $this->logger->pushProcessor(new WebProcessor());
        }

        /**
         * Destruye el recurso
         */
        public function __destroy()
        {
            fclose($this->stream);
        }

        /**
         * Método que escribe un log de información
         * @param string $msg
         * @param array $context
         *
         * @return bool
         */
        public function infoLog($msg = '', $context = array())
        {
            return $this->logger->addInfo($msg, $context);
        }

        /**
         * Método que escribe un log de Debug
         * @param string $msg
         * @param array $context
         *
         * @return bool
         */
        public function debugLog($msg = '', $context = array())
        {
            return $this->logger->addDebug($msg, $context);
        }

        /**
         * Método que escribe un log de Error
         * @param $msg
         * @param array $context
         *
         * @return bool
         */
        public function errorLog($msg, $context = array())
        {
            return $this->logger->addError($msg, $context);
        }
    }