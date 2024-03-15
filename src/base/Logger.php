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
    private function addPushLogger(string $logger, bool $debug): void
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
    private function setup(array $args = []): array
    {
        $args = $args ?? [];
        $debug = Config::getParam('debug');
        $namespace = self::DEFAULT_NAMESPACE;
        if (0 !== count($args)) {
            $namespace = $args[0][0] ?? 'PSFS';
            $debug = $args[0][1] ?? true;
        }
        $path = $this->createLoggerPath();
        return [$this->cleanLoggerName($namespace), $debug, $path];
    }

    /**
     * @return string
     */
    private function setLoggerName(): string
    {
        $logger = Config::getParam('platform.name', self::DEFAULT_NAMESPACE);
        return $this->cleanLoggerName($logger);
    }

    /**
     * @param $logger
     *
     * @return mixed
     */
    private function cleanLoggerName($logger): mixed
    {
        $logger = str_replace(' ', '', $logger);
        return preg_replace("/\\\\/", ".", $logger);
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
    public function addLog(string $msg, \Monolog\Level $type = \Monolog\Level::Notice, array $context = [], bool $force = false)
    {
        return !($this->checkLogLevel($type) || $force) || $this->logger->addRecord($type, $msg, $this->addMinimalContext($context));
    }

    /**
     * @param \Monolog\Level $level
     * @return bool
     */
    private function checkLogLevel(\Monolog\Level $level = \Monolog\Level::Notice): bool
    {
        switch ($this->logLevel) {
            case 'DEBUG':
                $logPass = \Monolog\Log::Debug;
                break;
            case 'INFO':
                $logPass = \Monolog\Level::Info;
                break;
            default:
            case 'NOTICE':
                $logPass = \Monolog\Level::Notice;
                break;
            case 'WARNING':
                $logPass = \Monolog\Level::Warning;
                break;
            case 'ERROR':
                $logPass = \Monolog\Level::Error;
                break;
            case 'EMERGENCY':
                $logPass = \Monolog\Level::Emergency;
                break;
            case 'ALERT':
                $logPass = \Monolog\Level::Alert;
                break;
            case 'CRITICAL':
                $logPass = \Monolog\Level::Critical;
                break;
        }
        return $logPass <= $level;
    }

    /**
     * @param string $msg
     * @param int $type
     * @param array|null $context
     * @param bool $force
     */
    public static function log(string $msg, int $type = LOG_DEBUG, array $context = null, bool $force = false): void
    {
        if (null === $context) {
            $context = [];
        }
        if (Config::getParam('profiling.enable') && 'DEBUG' === Config::getParam('log.level', 'NOTICE')) {
            Inspector::stats($msg, Inspector::SCOPE_DEBUG);
        }
        switch ($type) {
            case LOG_DEBUG:
                self::getInstance()->addLog($msg, \Monolog\Level::Debug, $context, $force);
                break;
            case LOG_WARNING:
                self::getInstance()->addLog($msg, \Monolog\Level::Warning, $context, $force);
                break;
            case LOG_CRIT:
                if (Config::getParam('log.slack.hook')) {
                    SlackHelper::getInstance()->trace($msg, '', '', $context);
                }
                self::getInstance()->addLog($msg, \Monolog\Level::Critical, $context, $force);
                break;
            case LOG_ERR:
                self::getInstance()->addLog($msg, \Monolog\Level::Error, $context, $force);
                break;
            case LOG_INFO:
                self::getInstance()->addLog($msg, \Monolog\Level::Info, $context, $force);
                break;
            case LOG_EMERG:
                self::getInstance()->addLog($msg, \Monolog\Level::Emergency, $context, $force);
                break;
            case LOG_ALERT:
                self::getInstance()->addLog($msg, \Monolog\Level::Alert, $context, $force);
                break;
            default:
                self::getInstance()->addLog($msg, \Monolog\Level::Notice, $context, $force);
                break;
        }
    }

    /**
     * @param bool $debug
     * @throws \Exception
     */
    private function addAwsStreamHandler($debug = false)
    {
        $client = new \Aws\CloudWatchLogs\CloudWatchLogsClient([
            'region' => Config::getParam('aws.region', 'eu-west-1'),
            'version' => Config::getParam('aws.version', 'latest'),
            'credentials' => [
                'key' => Config::getParam('aws.key', 'your AWS key'),
                'secret' => Config::getParam('aws.secret', 'your AWS secret'),
            ],
        ]);
        $stream = new \Maxbanton\Cwh\Handler\CloudWatch(
            $client,
            Config::getParam('aws.group_name', 'PSFS'),
            Config::getParam('platform.name', 'PSFS'),
            Config::getParam('aws.retention_days', 30),
            10000
        );
        $stream->setFormatter(new JsonFormatter);
        return $stream;
    }

    /**
     * @param bool $debug
     * @return StreamHandler
     * @throws \Exception
     */
    private function addDefaultStreamHandler(bool $debug = false): StreamHandler
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
     * @param array $context
     * @return array
     */
    private function addMinimalContext(array $context = [])
    {
        $context['uri'] = null !== $_SERVER && array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : 'Unknow';
        $context['method'] = null !== $_SERVER && array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : 'Unknow';
        if (null !== $_SERVER && array_key_exists('HTTP_X_PSFS_UID', $_SERVER)) {
            $context['uid'] = $_SERVER['HTTP_X_PSFS_UID'];
        }
        return $context;
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
