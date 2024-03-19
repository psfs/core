<?php

namespace PSFS\base;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\UidProcessor;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\LogHelper;
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
    protected \Monolog\Logger $logger;
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
    protected string $logLevel;

    /**
     * Logger constructor.
     * @throws GeneratorException
     * @throws ConfigException
     * @throws \Exception
     */
    public function __construct()
    {
        $args = func_get_args();
        list($logger, $debug, $path) = $this->setup($args);
        $this->stream = fopen($path . DIRECTORY_SEPARATOR . date('Ymd') . '.log', 'ab+');
        if (false !== $this->stream && is_resource($this->stream)) {
            $this->addPushLogger($logger, $debug);
        } else {
            throw new ConfigException(t('Error creating logger'));
        }
        $this->logLevel = strtoupper(Config::getParam('log.level', 'NOTICE'));
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * @param string $logger
     * @param boolean $debug
     * @throws \Exception
     */
    private function addPushLogger($logger, $debug)
    {
        $this->logger = new \Monolog\Logger(strtoupper($logger));
        $this->logger->pushHandler($this->addDefaultStreamHandler($debug));
        if ($debug) {
            $phpFireLog = Config::getParam('logger.phpFire');
            if (!empty($phpFireLog)) {
                $this->logger->pushHandler(new FirePHPHandler());
            }
            $memoryLog = Config::getParam('logger.memory');
            if (!empty($memoryLog)) {
                $this->logger->pushProcessor(new MemoryUsageProcessor());
            }
        }
        $this->uuid = new UidProcessor();
        $this->logger->pushProcessor($this->uuid);
    }

    /**
     * @param array $args
     * @return array
     * @throws exception\GeneratorException
     */
    private function setup(array $args = [])
    {
        $args = $args ?? [];
        $debug = Config::getParam('debug');
        $namespace = self::DEFAULT_NAMESPACE;
        if (0 !== count($args)) {
            $namespace = $args[0][0] ?? 'PSFS';
            $debug = $args[0][1] ?? true;
        }
        $path = $this->createLoggerPath();
        return [LogHelper::cleanLoggerName($namespace), $debug, $path];
    }

    /**
     * @return string
     */
    private function setLoggerName(): string
    {
        $logger = Config::getParam('platform.name', self::DEFAULT_NAMESPACE);
        return LogHelper::cleanLoggerName($logger);
    }

    /**
     * @return string
     * @throws exception\GeneratorException
     */
    private function createLoggerPath(): string
    {
        $logger = $this->setLoggerName();
        $path = Config::getParam('default.log.path', LOG_DIR) . DIRECTORY_SEPARATOR . $logger . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        GeneratorHelper::createDir($path);
        return $path;
    }

    /**
     * @param string $msg
     * @param \Monolog\Level $type
     * @param array $context
     * @param boolean $force
     * @return bool
     */
    public function addLog($msg, $type = \Monolog\Level::Notice, $context = [], $force = false)
    {
        return !(LogHelper::checkLogLevel($this->logLevel, $type) || $force) || $this->logger->addRecord($type, $msg, LogHelper::addMinimalContext($context));
    }

    /**
     * @param string $msg
     * @param int $type
     * @param array|null $context
     * @param bool $force
     */
    public static function log($msg, $type = LOG_DEBUG, $context = null, $force = false): void
    {
        if (null === $context) {
            $context = [];
        }
        if (Config::getParam('profiling.enable') && 'DEBUG' === Config::getParam('log.level', 'NOTICE')) {
            Inspector::stats($msg, Inspector::SCOPE_DEBUG);
        }
        $level = LogHelper::calculateLogLevel($type);
        if (in_array($level, [\Monolog\Level::Critical, \Monolog\Level::Error, \Monolog\Level::Emergency]) &&
            strlen(Config::getParam('log.slack.hook', '')) > 0) {
            SlackHelper::getInstance()->trace($msg, '', '', $context);
        }
        self::getInstance()->addLog($msg, \Monolog\Level::Notice, $context, $force);
    }

    /**
     * @param bool $debug
     * @return StreamHandler
     * @throws \Exception
     */
    private function addDefaultStreamHandler($debug = false): StreamHandler
    {
        // the default date format is "Y-m-d H:i:s"
        $dateFormat = 'Y-m-d H:i:s.u';
        // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        $output = "[%datetime%] [%channel%:%level_name%]\t%message%\t%context%\t%extra%\n";
        // finally, create a formatter
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler($this->stream, $debug ? \Monolog\Level::Debug : \Monolog\Level::Warning);
        $stream->setFormatter($formatter);
        return $stream;
    }

    /**
     * @return string
     */
    public function getLogUid()
    {
        return $this->uuid->getUid();
    }

    /**
     * @return string
     */
    public static function getUid()
    {
        return self::getInstance()->getLogUid();
    }
}
