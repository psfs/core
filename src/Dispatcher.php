<?php
/**
 * @author Fran López <fran.lopez84@hotmail.es>
 * @version 1.0
 */

namespace PSFS;

use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\exception\RouterException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\controller\ConfigController;
use PSFS\controller\UserController;

/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton
{
    /**
     * @Inyectable
     * @var \PSFS\base\Security $security
     */
    protected $security;
    /**
     * @Inyectable
     * @var \PSFS\base\Router $router
     */
    protected $router;
    /**
     * @Inyectable
     * @var \PSFS\base\Request $parser
     */
    protected $parser;
    /**
     * @Inyectable
     * @var \PSFS\base\Logger $log
     */
    protected $log;
    /**
     * @Inyectable
     * @var \PSFS\base\config\Config $config
     */
    protected $config;

    protected $ts;
    protected $mem;

    private $actualUri;

    /**
     * Initializer method
     */
    public function init()
    {
        Logger::log('Dispatcher init');
        parent::init();
        $this->initiateStats();
        I18nHelper::setLocale();
        $this->bindWarningAsExceptions();
        $this->actualUri = $this->parser->getServer("REQUEST_URI");
        Logger::log('End dispatcher init');
    }

    /**
     * Run method
     * @return string HTML
     */
    public function run()
    {
        Logger::log('Begin runner');
        try {
            if ($this->config->isConfigured()) {
                if (!$this->parser->isFile()) {
                    return $this->router->execute($this->actualUri);
                }
            } else {
                return ConfigController::getInstance()->config();
            }
        } catch (AdminCredentialsException $a) {
            return UserController::showAdminManager();
        } catch (SecurityException $s) {
            return $this->security->notAuthorized($this->actualUri);
        } catch (RouterException $r) {
            return $this->router->httpNotFound($r);
        } catch (\Exception $e) {
            return $this->dumpException($e);
        }
    }

    /**
     * Method that convert an exception to html
     *
     * @param \Exception $e
     *
     * @return string HTML
     */
    protected function dumpException(\Exception $e)
    {
        Logger::log('Starting dump exception');
        $ex = (NULL !== $e->getPrevious()) ? $e->getPrevious() : $e;
        $error = array(
            "error" => $ex->getMessage(),
            "file" => $ex->getFile(),
            "line" => $ex->getLine(),
        );
        Logger::log('Throwing exception', LOG_ERR, $error);
        unset($error);

        return $this->router->httpNotFound($ex);
    }

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
    private function initiateStats()
    {
        Logger::log('Initializing stats (mem + ts)');
        if (null !== $_SERVER && array_key_exists('REQUEST_TIME_FLOAT', $_SERVER)) {
            $this->ts = (float)$_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $this->ts = $this->parser->getTs();
        }
        $this->mem = memory_get_usage();
    }

}
