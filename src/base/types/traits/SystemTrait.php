<?php

namespace PSFS\base\types\traits;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ServerHelper;
use PSFS\base\types\helpers\SlackHelper;

/**
 * @package PSFS\base\types\traits
 */
trait SystemTrait
{
    use BoostrapTrait;
    private static bool $shutdownHandlerBound = false;

    /**
     * @var integer
     */
    protected $ts;
    /**
     * @var integer
     */
    protected $mem;

    /**
     *
     * @param $unit string
     *
     * @return int
     */
    public function getMem($unit = "Bytes")
    {
        // Keep same mode as bootstrap baseline (PSFS_START_MEM uses memory_get_usage(true)).
        $use = memory_get_usage(true) - $this->mem;
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
     * @return double
     */
    public function getTs()
    {
        return microtime(true) - $this->ts;
    }


    protected function bindWarningAsExceptions()
    {
	    Inspector::stats('[SystemTrait] Added handlers for errors', Inspector::SCOPE_DEBUG);

	    if (Config::getParam('debug')) {
		    ini_set('display_errors', 1);
	    }

	    // Warning & Notice handler
	    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		    if (!(error_reporting() & $errno)) {
			    return true;
		    }

		    Logger::log($errstr, LOG_CRIT, [
			    'file' => $errfile,
			    'line' => $errline,
			    'errno' => $errno
		    ]);

		    return true;
	    }, E_ALL);

	    if (!self::$shutdownHandlerBound) {
		    self::$shutdownHandlerBound = true;
		    register_shutdown_function([$this, 'handleShutdown']);
	    }
    }

	public function handleShutdown(): void
	{
		$error = error_get_last();

		// Normalize errors while preserving the original fallback order
		if ($error === null && json_last_error() !== JSON_ERROR_NONE) {
			$error = [
				'type' => E_WARNING,
				'file' => '',
				'line' => 0,
				'message' => json_last_error_msg()
			];
		}

		if ($error === null && preg_last_error() !== PREG_NO_ERROR) {
			$error = [
				'type' => E_WARNING,
				'file' => '',
				'line' => 0,
				'message' => preg_last_error_msg()
			];
		}

		if ($error === null) {
			$dateErrors = \DateTime::getLastErrors();

			if ($dateErrors !== false &&
				($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) {
				$error = [
					'type' => E_WARNING,
					'file' => '',
					'line' => 0,
					'message' => 'DateTime errors detected'
				];
			}
		}

		// Log errors respecting the configured error_reporting level
		if ($error !== null && (error_reporting() & $error['type'])) {
			$errno = $error['type'];
			$errfile = $error['file'];
			$errline = $error['line'];
			$errstr = $error['message'];

			Logger::log($errstr, LOG_CRIT, [
				'file' => $errfile,
				'line' => $errline,
				'errno' => $errno
			]);

			if (null !== Config::getParam('log.slack.hook')) {
				SlackHelper::getInstance()->trace(
					$errstr,
					$errfile,
					$errline,
					$error
				);
			}
		}

		// Notify about slow service endpoints
		if ($this->getTs() > 10 && null !== Config::getParam('log.slack.hook')) {
			SlackHelper::getInstance()->trace('Slow service endpoint', '', '', [
				'time' => round($this->getTs(), 3) . ' secs',
				'memory' => round($this->getMem('MBytes'), 3) . ' Mb',
			]);
		}
	}


    protected function initiateStats(): void
    {
        Inspector::stats('[SystemTrait] Initializing stats (mem + ts)');
        if (ServerHelper::hasServerValue('REQUEST_TIME_FLOAT')) {
            $this->ts = (float)ServerHelper::getServerValue('REQUEST_TIME_FLOAT');
        } else {
            $this->ts = PSFS_START_TS;
        }
        $this->mem = PSFS_START_MEM;
    }
}
