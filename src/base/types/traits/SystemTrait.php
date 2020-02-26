<?php
namespace PSFS\base\types\traits;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\SlackHelper;

/**
 * Class SystemTrait
 * @package PSFS\base\types\traits
 */
Trait SystemTrait {
    use BoostrapTrait;

    /**
     * @var integer
     */
    protected $ts;
    /**
     * @var integer
     */
    protected $mem;

    /**
     * Method that returns the memory used at this specific moment
     *
     * @param $unit string
     *
     * @return int
     */
    public function getMem($unit = "Bytes")
    {
        $use = memory_get_usage() - $this->mem;
        switch ($unit) {
            case "KBytes":
                $use /= 1024;
                break;
            case "MBytes":
                $use /= (1024 * 1024);
                break;
            case "Bytes":
            default:
        }

        return $use;
    }

    /**
     * Method that returns the seconds spent with the script
     * @return double
     */
    public function getTs()
    {
        return microtime(TRUE) - $this->ts;
    }

    /**
     * Debug function to catch warnings as exceptions
     */
    protected function bindWarningAsExceptions()
    {
        Inspector::stats('[SystemTrait] Added handlers for errors', Inspector::SCOPE_DEBUG);
        if(Config::getParam('debug')) {
            Logger::log('Setting error_reporting as E_ALL');
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 1);
        }
        //Warning & Notice handler
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            Logger::log($errstr, LOG_CRIT, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
            return true;
        }, E_ALL | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);

        register_shutdown_function(function () {
            $error = error_get_last() or json_last_error() or preg_last_error() or \DateTime::getLastErrors();
            if( $error !== NULL) {
                $errno   = $error["type"];
                $errfile = $error["file"];
                $errline = $error["line"];
                $errstr  = $error["message"];
                Logger::log($errstr, LOG_CRIT, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
                if(null !== Config::getParam('log.slack.hook')) {
                    SlackHelper::getInstance()->trace($errstr, $errfile, $errline, $error);
                }
            }

            if(self::getTs() > 10 && null !== Config::getParam('log.slack.hook')) {
                SlackHelper::getInstance()->trace('Slow service endpoint', '', '', [
                    'time' => round(self::getTs(), 3) . ' secs',
                    'memory' => round(self::getMem('MBytes'), 3) . ' Mb',
                ]);
            }
            return false;
        });
    }

    /**
     * Stats initializer
     */
    protected function initiateStats()
    {
        Inspector::stats('[SystemTrait] Initializing stats (mem + ts)');
        if (null !== $_SERVER && array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)) {
            $this->ts = (float)$_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->ts = PSFS_START_TS;
        }
        $this->mem = PSFS_START_MEM;
    }
}
