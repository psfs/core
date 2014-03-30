<?php

namespace PSFS\config;

use PSFS\base\Singleton;
use PSFS\excetion\ConfigException;

/**
 * Class Config
 * @package PSFS\config
 */
class Config extends Singleton{

    const DEFAULT_LANGUAGE = 'es';
    const DEFAULT_ENCODE = 'UTF-8';
    const DEFAULT_CTYPE = 'text/html';
    const DEFAULT_DATETIMEZONE = 'Europe/Madrid';

    protected $config;
    static public $required = array('db_host', 'db_port', 'db_user', 'db_password');
    protected $debug = false;

    function __construct()
    {
        $this->configure();
    }

    /**
     * Método que carga la configuración del sistema
     * @param null $path
     *
     * @return mixed
     */
    protected function configure()
    {
        if(file_exists(CONFIG_DIR . '/config.json'))
        {
            $config = json_decode(file_get_contents(CONFIG_DIR . '/config.json'), true);
            $this->config["DEBUG"] = $config["debug"] ?: false;
        }else{
            $this->debug = true;
        }
        return this;
    }

    /**
     * Método que devuelve si la plataforma está en modo debug
     * @return bool
     */
    public function getDebugMode(){ return $this->debug; }

    /**
     * Método que devuelve el path de la carpeta lib
     * @return string
     */
    public function getLibPath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' - DIRECTORY_SEPARATOR); }

    /**
     * Método que devuelve el path de cache
     * @return string
     */
    public function getCachePath(){ return CACHE_DIR; }

    /**
     * Método que devuelve el path general de templates de PSFS
     * @return string
     */
    public function getTemplatePath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR ); }

    /**
     * Método que indica si se ha configurado correctamente la plataforma
     * @return bool
     */
    public function isConfigured()
    {
        $configured = true;
        foreach(static::$required as $required)
        {
            if(empty($this->config[$required]))
            {
                $configured = false;
                break;
            }
        }
        return $configured;
    }
}