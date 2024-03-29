<?php
namespace PSFS\base\types\helpers;

use Monolog\Level;

class LogHelper {
    /**
     * @param string $currentLogLevel
     * @param Level $level
     * @return bool
     */
    public static function checkLogLevel(string $currentLogLevel, \Monolog\Level $level = \Monolog\Level::Notice): bool
    {
        switch ($currentLogLevel) {
            case 'DEBUG':
                $logPass = \Monolog\Level::Debug;
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
        return $logPass->value <= $level->value;
    }

    /**
     * @param $logger
     *
     * @return mixed
     */
    public static function cleanLoggerName($logger): mixed
    {
        $logger = str_replace(' ', '', $logger);
        return preg_replace("/\\\\/", ".", $logger);
    }

    /**
     * @param array $context
     * @return array
     */
    public static function addMinimalContext(array $context = []): array
    {
        $context['uri'] = ServerHelper::getServerValue('REQUEST_URI', 'Unknow');
        $context['method'] = ServerHelper::getServerValue('REQUEST_METHOD', 'Unknow');
        if (null !== ServerHelper::getServerValue('HTTP_X_PSFS_UID')) {
            $context['uid'] = ServerHelper::getServerValue('HTTP_X_PSFS_UID');
        }
        return $context;
    }

    /**
     * @param int $type
     * @return \Monolog\Level
     */
    public static function calculateLogLevel(int $type): \Monolog\Level
    {
        switch ($type) {
            case LOG_DEBUG:
                $level = \Monolog\Level::Debug;
                break;
            case LOG_WARNING:
                $level = \Monolog\Level::Warning;
                break;
            case LOG_CRIT:
                $level = \Monolog\Level::Critical;
                break;
            case LOG_ERR:
                $level = \Monolog\Level::Error;
                break;
            case LOG_INFO:
                $level = \Monolog\Level::Info;
                break;
            case LOG_EMERG:
                $level = \Monolog\Level::Emergency;
                break;
            case LOG_ALERT:
                $level = \Monolog\Level::Alert;
                break;
            default:
                $level = \Monolog\Level::Notice;
                break;
        }
        return $level;
    }
}
