<?php
namespace PSFS\base\types\traits;

use PSFS\base\Logger;
use PSFS\base\Request;

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
        Logger::log('Added handlers for errors');
        //Warning & Notice handler
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            Logger::log($errstr, LOG_CRIT, ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
            return true;
        }, E_ALL | E_STRICT);
    }

    /**
     * Stats initializer
     */
    protected function initiateStats()
    {
        Logger::log('Initializing stats (mem + ts)');
        if (null !== $_SERVER && array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)) {
            $this->ts = (float)$_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->ts = Request::getInstance()->getTs();
        }
        $this->mem = memory_get_usage();
    }
}