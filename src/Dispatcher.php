<?php

namespace PSFS;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\RouterException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Singleton;

require_once "bootstrap.php";
/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton {
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
    protected $locale = "es_ES";

    private $actualUri;

    /**
     * Constructor por defecto
     * @param $mem
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Método de inicialización del Dispatcher
     */
    public function init() {
        parent::init();
        $this->ts = $this->parser->getTs();
        $this->mem = memory_get_usage();
        $this->setLocale();
        $this->bindWarningAsExceptions();
        $this->actualUri = $this->parser->getServer("REQUEST_URI");
    }

    /**
     * Método que asigna el directorio de traducciones para el proyecto
     * @return $this
     */
    private function setLocale() {
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
    public function run() {
        $this->log->infoLog("Inicio petición ".$this->parser->getRequestUri());
        //
        try {
            if ($this->config->isConfigured()) {
                if (!$this->parser->isFile()) {
                    return $this->router->execute($this->actualUri);
                }
            } else {
                return $this->router->getAdmin()->config();
            }
        }catch (ConfigException $c) {
            return $this->dumpException($c);
        }catch (SecurityException $s) {
            return $this->security->notAuthorized($this->actualUri);
        } catch(RouterException $r) {
            return $this->router->httpNotFound($r);
        }catch (\Exception $e) {
            return $this->dumpException($e);
        }
    }

    /**
     * Método que parsea una excepción y la devuelve por pantalla
     * @param \Exception $e
     * @return string HTML
     */
    protected function dumpException(\Exception $e) {
        $ex = (null !== $e->getPrevious()) ? $e->getPrevious() : $e;
        $error = array(
            "error" => $ex->getMessage(),
            "file" => $ex->getFile(),
            "line" => $ex->getLine(),
        );
        $this->log->errorLog(json_encode($error));
        unset($error);
        return $this->router->httpNotFound($ex);
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
     * @return double
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
        if ($this->config->getDebugMode())
        {
            //Warning & Notice handler
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 500, $errno, $errfile, $errline);
            });
        }
    }

}
