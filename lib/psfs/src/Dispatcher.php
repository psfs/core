<?php

namespace PSFS;

use PSFS\base\Forms;
use PSFS\exception\ConfigException;
use PSFS\exception\LoggerException;

/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends \PSFS\base\Singleton{
    private $router;
    private $parser;
    private $security;
    private $log;
    private $config;

    protected $ts;
    protected $mem;

    /**
     * Constructor por defecto
     * @param $mem
     */
    public function __construct($mem = 0){
        $this->router = \PSFS\base\Router::getInstance();
        $this->parser = \PSFS\base\Request::getInstance();
        $this->security = \PSFS\base\Security::getInstance();
        $this->log = \PSFS\base\Logger::getInstance();
        $this->ts = $this->parser->getTs();
        $this->mem = memory_get_usage();
        $this->config = \PSFS\config\Config::getInstance();
    }

    /**
     * Método inicial
     */
    public function run()
    {
        $this->log->infoLog("Inicio petición ".$this->parser->getrequestUri());
        if(!$this->config->isConfigured()) return $this->splashConfigure();
        //
        try{
            if(!$this->parser->isFile())
            {
                pre("HOla que ase");
            }else $this->router->httpNotFound();
        }catch(ConfigException $ce)
        {
            return $this->splashConfigure();
        }
        catch(Exception $e)
        {
            $this->log->errorLog($e);
            return $this->router->httpNotFound();
        }
    }

    /**
     * Método que devuelve la memoria usada desde la ejecución
     * @param $formatted
     *
     * @return int
     */
    public function getMem($unit = "Bytes")
    {
        $use = memory_get_usage() - $this->mem;
        switch($unit)
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

    private function splashConfigure()
    {
        $this->log->infoLog("Arranque del Config Loader al solicitar ".$this->parser->getrequestUri());
        return \PSFS\Base\Template::getInstance()->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
        ));
    }
}