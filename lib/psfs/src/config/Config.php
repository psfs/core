<?php

namespace PSFS\config;

use PSFS\base\Singleton;

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
        if(!file_exists(CONFIG_DIR . '/config.json')) throw new \Exception('Se requiere un fichero de configuración', 500);
        $config = json_decode(file_get_contents(CONFIG_DIR . '/config.json'), true);
        $this->config["DEBUG"] = $config["debug"] ?: false;
        return this;
    }

    /**
     * Método que devuelve si la plataforma está en modo debug
     * @return bool
     */
    public function getDebugMode(){ return $this->config["DEBUG"] ?: false; }

    /**
     * Método que devuelve el path de la carpeta lib
     * @return string
     */
    public function getLibPath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' - DIRECTORY_SEPARATOR); }

    public function getCachePath(){ return CACHE_DIR; }
    public function getTemplatePath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR ); }
}