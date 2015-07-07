<?php

namespace PSFS;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Singleton;

require_once "bootstrap.php";
/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton {
    private $router;
    private $parser;
    private $security;
    private $log;
    private $config;

    protected $ts;
    protected $mem;
    protected $locale = "es_ES";

    /**
     * Constructor por defecto
     * @param $mem
     */
    public function __construct() {
        $this->router = Router::getInstance();
        $this->parser = Request::getInstance();
        $this->security = Security::getInstance();
        $this->config = Config::getInstance();
        $this->log = Logger::getInstance('PSFS', $this->config->getDebugMode());
        $this->ts = $this->parser->getTs();
        $this->mem = memory_get_usage();
        $this->setLocale();
    }

    /**
     * Método que asigna el directorio de traducciones para el proyecto
     * @return $this
     */
    private function setLocale()
    {
        $this->locale = $this->config->get("default_language");
        //Cargamos traducciones
        putenv("LC_ALL=".$this->locale);
        setlocale(LC_ALL, $this->locale);
        //Cargamos el path de las traducciones
        $locale_path = BASE_DIR.DIRECTORY_SEPARATOR.'locale';
        Config::createDir($locale_path);
        bindtextdomain('translations', $locale_path);
        textdomain('translations');
        bind_textdomain_codeset('translations', 'UTF-8');
        return $this;
    }

    /**
     * Método inicial
     */
    public function run()
    {
        $this->log->infoLog("Inicio petición ".$this->parser->getrequestUri());
        if (!$this->config->isConfigured()) return $this->router->getAdmin()->config();
        //
        try {
            if (!$this->parser->isFile())
            {
                if ($this->config->getDebugMode())
                {
                    $this->bindWarningAsExceptions();
                }
                if (!$this->router->execute($this->parser->getServer("REQUEST_URI"))) return $this->router->httpNotFound();
            }else return $this->router->httpNotFound();
        }catch (ConfigException $c) {
            return $this->config->config();
        }catch (SecurityException $s) {
            return $this->security->notAuthorized($this->parser->getServer("REQUEST_URI"));
        }catch (\Exception $e) {
            $error = array(
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            );
            $this->log->errorLog(json_encode($error));
            unset($error);
            return $this->router->httpNotFound($e);
        }
        return false;
    }

    /**
     * Método que devuelve la memoria usada desde la ejecución
     * @param $unit string
     *
     * @return int
     */
    public function getMem($unit = "Bytes")
    {
        $use = memory_get_usage() - $this->mem;
        switch ($unit)
        {
            case "KBytes": $use /= 1024; break;
            case "MBytes": $use /= (1024*1024); break;
            case "Bytes":
            default:
        }
        return $use;
    }

    /**
     * Método que devuelve el tiempo pasado desde el inicio del script
     * @return mixed
     */
    public function getTs()
    {
        return microtime(true) - $this->ts;
    }

    /**
     * Función debug para capturar los warnings y notice y convertirlos en excepciones
     */
    protected function bindWarningAsExceptions()
    {
        //Warning & Notice handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 500, $errno, $errfile, $errline);
        }, E_WARNING);
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 500, $errno, $errfile, $errline);
        }, E_NOTICE);
    }

}
