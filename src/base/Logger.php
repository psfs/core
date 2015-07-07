<?php

    namespace PSFS\base;

    use Monolog\Handler\FirePHPHandler;
    use Monolog\Handler\StreamHandler;
    use Monolog\Logger as Monolog;
    use Monolog\Processor\MemoryUsageProcessor;
    use Monolog\Processor\WebProcessor;
    use PSFS\base\config\Config;


    if (!defined("LOG_DIR"))
    {
        Config::createDir(BASE_DIR.DIRECTORY_SEPARATOR.'logs');
        define("LOG_DIR", BASE_DIR.DIRECTORY_SEPARATOR.'logs');
    }

    /**
     * Class Logger
     * @package PSFS\base
     * Servicio de log
     */
    class Logger extends Singleton {

        protected $logger;
        private $stream;

        /**
         * @internal param string $path
         */
        public function __construct()
        {
            $args = func_get_args();
            $logger = 'general';
            $debug = false;
            if (!empty($args))
            {
                if (isset($args[0][0])) $logger = $args[0][0];
                if (isset($args[0][1])) $debug = $args[0][1];
            }
            $path = LOG_DIR.DIRECTORY_SEPARATOR.$logger.DIRECTORY_SEPARATOR.date('Y').DIRECTORY_SEPARATOR.date('m');
            Config::createDir($path);
            $this->stream = fopen($path.DIRECTORY_SEPARATOR.date("Ymd").".log", "a+");
            $this->logger = new Monolog(strtoupper($logger));
            $this->logger->pushHandler(new StreamHandler($this->stream));
            $config = Config::getInstance();
            if ($debug)
            {
                $phpFireLog = $config->get("logger.phpFire");
                if (!empty($phpFireLog)) {
                    $this->logger->pushHandler(new FirePHPHandler());
                }
                $memoryLog = $config->get("logger.memory");
                if (!empty($memoryLog)) {
                    $this->logger->pushProcessor(new MemoryUsageProcessor());
                }
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

        /**
         * Método que escribe un log de Warning
         * @param $msg
         * @param array $context
         * @return bool
         */
        public function warningLog($msg, $context = array()) {
            return $this->logger->addWarning($msg, $context);
        }
    }
