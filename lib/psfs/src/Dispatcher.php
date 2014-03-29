<?php

namespace PSFS;

use PSFS\base\Singleton;
use PSFS\base\Router;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Logger;
use PSFS\Base\Template;

/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton{
    private $router;
    private $parser;
    private $security;
    private $log;

    protected $ts;
    protected $mem;

    /**
     * Constructor por defecto
     * @param $mem
     */
    public function __construct($mem = 0){
        $this->router = Router::getInstance();
        $this->parser = Request::getInstance();
        $this->security = Security::getInstance();
        $this->log = Logger::getInstance();
        $this->ts = $this->parser->getTs();
        $this->mem = memory_get_usage();
    }

    /**
     * Método inicial
     */
    public function run()
    {
        $this->log->infoLog("Inicio petición ".$this->parser->getrequestUri());
        try{
            return Template::getInstance()->render("welcome.html.twig", array("text" => 'Hola que ase'));
        }catch(Exception $e)
        {
            $this->log->errorLog($e);
            return $this->router->httpNotFound();
        }
        //
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

    public function getTs()
    {
        return microtime(true) - $this->ts;
    }
}