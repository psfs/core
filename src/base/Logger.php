<?php

namespace PSFS\base;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\UidProcessor;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\SlackHelper;
use PSFS\base\types\traits\SingletonTrait;


if (!defined('LOG_DIR')) {
    GeneratorHelper::createDir(BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
    define('LOG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
}

/**
 * Class Logger
 * @package PSFS\base
 * Servicio de log
 */
class Logger
{
    const DEFAULT_NAMESPACE = 'PSFS';
    use SingletonTrait;
    /**
     * @var \Monolog\Logger
     */
    protected $logger;
    /**
     * @var resource
     */
    private $stream;
    /**
     * @var UidProcessor
     */
    private $uuid;
    /**
     * @var string
     */
    protected $log_level;

    /**
     * Logger constructor.
     * @throws exception\GeneratorException
     */
    public function __construct()
    {
        $config = Config::getInstance();
        $args = func_get_args();
        list($logger, $debug, $path) = $this->setup($config, $args);
        $this->stream = fopen($path . DIRECTORY_SEPARATOR . date('Ymd') . '.log', 'a+');
        $this->addPushLogger($logger, $debug, $config);
        $this->log_level = Config::getParam('log.level', 'info');
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * @param string $msg
     * @param array $context
     * @return bool
     */
    public function defaultLog($msg = '', array $context = [])
    {
        return $this->logger->addNotice($msg, $this->addMinimalContext($context));
    }

    /**
     * @param string $msg
     * @param array $context
     *
     * @return bool
     */
    public function infoLog($msg = '', array $context = [])
    {
        return $this->logger->addInfo($msg, $this->addMinimalContext($context));
    }

    /**
     * @param string $msg
     * @param array $context
     *
     * @return bool
     */
    public function debugLog($msg = '', array $context = [])
    {
        return ($this->log_level === 'debug') ? $this->logger->addDebug($msg, $this->addMinimalContext($context)) : null;
    }

    /**
     * @param $msg
     * @param array $context
     *
     * @return bool
     */
    public function errorLog($msg, array $context = [])
    {
        return $this->logger->addError($msg, $this->addMinimalContext($context));
    }

    /**
     * @param $msg
     * @param array $context
     *
     * @return bool
     */
    public function criticalLog($msg, array $context = [])
    {
        if(Config::getParam('log.slack.hook')) {
            SlackHelper::getInstance()->trace($msg, '', '', $context);
        }
        return $this->logger->addCritical($msg, $this->addMinimalContext($context));
    }

    /**
     * @param $msg
     * @param array $context
     * @return bool
     */
    public function warningLog($msg, array $context = [])
    {
        return $this->logger->addWarning($msg, $this->addMinimalContext($context));
    }

    /**
     * @param string $logger
     * @param boolean $debug
     * @param Config $config
     * @throws \Exception
     */
    private function addPushLogger($logger, $debug, Config $config)
    {
        $this->logger = new Monolog(strtoupper($logger));
        $this->logger->pushHandler($this->addDefaultStreamHandler($debug));
        if ($debug) {
            $phpFireLog = $config->get('logger.phpFire');
            if (!empty($phpFireLog)) {
                $this->logger->pushHandler(new FirePHPHandler());
            }
            $memoryLog = $config->get('logger.memory');
            if (!empty($memoryLog)) {
                $this->logger->pushProcessor(new MemoryUsageProcessor());
            }
        }
        $this->uuid = new UidProcessor();
        $this->logger->pushProcessor($this->uuid);
    }

    /**
     * @param Config $config
     * @param array $args
     * @return array
     * @throws exception\GeneratorException
     */
    private function setup(Config $config, array $args = [])
    {
        $args = $args ?? [];
        $debug = $config->getDebugMode();
        $namespace = self::DEFAULT_NAMESPACE;
        if (0 !== count($args)) {
            $namespace = $args[0][0] ?? 'PSFS';
            $debug = $args[0][1] ?? true;
        }
        $path = $this->createLoggerPath($config);
        return array($this->cleanLoggerName($namespace), $debug, $path);
    }

    /**
     * @param Config $config
     *
     * @return string
     */
    private function setLoggerName(Config $config)
    {
        $logger = $config->get('platform_name') ?: self::DEFAULT_NAMESPACE;
        $logger = $this->cleanLoggerName($logger);

        return $logger;
    }

    /**
     * @param $logger
     *
     * @return mixed
     */
    private function cleanLoggerName($logger)
    {
        $logger = str_replace(' ', '', $logger);
        $logger = preg_replace('/\\\/', ".", $logger);

        return $logger;
    }

    /**
     * @param Config $config
     * @return string
     * @throws exception\GeneratorException
     */
    private function createLoggerPath(Config $config)
    {
        $logger = $this->setLoggerName($config);
        $path = LOG_DIR . DIRECTORY_SEPARATOR . $logger . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        GeneratorHelper::createDir($path);

        return $path;
    }

    /**
     * @param string $msg
     * @param int $type
     * @param array $context
     */
    public static function log($msg, $type = LOG_DEBUG, array $context = null)
    {
        if(null === $context) {
            $context = [];
        }
        if(Config::getParam('profiling.enable')) {
            Inspector::stats($msg);
        }
        switch ($type) {
            case LOG_DEBUG:
                self::getInstance()->debugLog($msg, $context);
                break;
            case LOG_WARNING:
                self::getInstance()->warningLog($msg, $context);
                break;
            case LOG_CRIT:
                self::getInstance()->criticalLog($msg, $context);
                break;
            case LOG_ERR:
                self::getInstance()->errorLog($msg, $context);
                break;
            case LOG_INFO:
                self::getInstance()->infoLog($msg, $context);
                break;
            default:
                self::getInstance()->defaultLog($msg, $context);
                break;
        }
    }

    /**
     * @param bool $debug
     * @return StreamHandler
     * @throws \Exception
     */
    private function addDefaultStreamHandler($debug = false)
    {
        // the default date format is "Y-m-d H:i:s"
        $dateFormat = 'Y-m-d H:i:s.u';
        // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        $output = "[%datetime%] [%channel%:%level_name%]\t%message%\t%context%\t%extra%\n";
        // finally, create a formatter
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler($this->stream, $debug ? Monolog::DEBUG : Monolog::WARNING);
        $stream->setFormatter($formatter);
        return $stream;
    }

    /**
     * @param array $context
     * @return array
     */
    private function addMinimalContext(array $context = [])
    {
        $context['uri'] = null !== $_SERVER && array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : 'Unknow';
        $context['method'] = null !== $_SERVER && array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : 'Unknow';
        if(null !== $_SERVER && array_key_exists('HTTP_X_PSFS_UID', $_SERVER)) {
            $context['uid'] = $_SERVER['HTTP_X_PSFS_UID'];
        }
        return $context;
    }

    /**
     * @return string
     */
    public function getLogUid() {
        return $this->uuid->getUid();
    }

    /**
     * @return string
     */
    public static function getUid() {
        return self::getInstance()->getLogUid();
    }
}
