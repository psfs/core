<?php

namespace PSFS\base\config;


use PSFS\base\types\SingletonTrait;


/**
 * Class Config
 * @package PSFS\base\config
 */
class Config {

    use SingletonTrait;
    const DEFAULT_LANGUAGE = 'es';
    const DEFAULT_ENCODE = 'UTF-8';
    const DEFAULT_CTYPE = 'text/html';
    const DEFAULT_DATETIMEZONE = 'Europe/Madrid';

    protected $config;
    static public $defaults = array(
        "db_host" => "localhost",
        "db_port" => "3306",
        "default_language" => "es_ES",
    );
    static public $required = array('db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'home_action', 'default_language');
    static public $encrypted = array('db_password');
    static public $optional = array('platform_name', 'debug', 'restricted', 'admin_login', 'logger.phpFire', 'logger.memory', 'poweredBy', 'author', 'author_email', 'version');
    protected $debug = false;

    /**
     */
    public function __construct()
    {
        $this->configure();
    }

    /**
     * Método que carga la configuración del sistema
     * @return Config
     * @internal param null $path
     *
     */
    protected function configure()
    {
        if (file_exists(CONFIG_DIR.'/config.json'))
        {
            $this->config = json_decode(file_get_contents(CONFIG_DIR.DIRECTORY_SEPARATOR.'config.json'), true) ?: array();
            $this->debug = (isset($this->config['debug'])) ? (bool)$this->config['debug'] : false;
        }else {
            $this->debug = true;
        }
        return $this;
    }

    /**
     * Método que devuelve si la plataforma está en modo debug
     * @return bool
     */
    public function getDebugMode() { return $this->debug; }

    /**
     * Método que devuelve el path de cache
     * @return string
     */
    public function getCachePath() { return CACHE_DIR; }

    /**
     * Método que devuelve el path general de templates de PSFS
     * @return string
     */
    public function getTemplatePath() { return realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR); }

    /**
     * Método que indica si se ha configurado correctamente la plataforma
     * @return bool
     */
    public function isConfigured()
    {
        $configured = true;
        foreach (static::$required as $required)
        {
            if (empty($this->config[$required]))
            {
                $configured = false;
                break;
            }
        }
        return $configured;
    }

    /**
     * Método que guarda la configuración del framework
     *
     * @param array $data
     * @param array|null $extra
     * @return bool
     */
    public static function save(array $data, array $extra = null)
    {
        //En caso de tener parámetros nuevos los guardamos
        if (!empty($extra["label"])) {
            foreach ($extra["label"] as $index => $field)
        {
            if (isset($extra["value"][$index]) && !empty($extra["value"][$index])) {
                /** @var $data array */
                $data[$field] = $extra["value"][$index];
            }
        }
        }
        $final_data = array();
        if (!empty($data)) {
            foreach ($data as $key => $value)
        {
            if (!empty($value)) {
                $final_data[$key] = $value;
            }
        }
        }
        return (false !== file_put_contents(CONFIG_DIR.DIRECTORY_SEPARATOR.'config.json', json_encode($final_data, JSON_PRETTY_PRINT)));
    }

    /**
     * Método que devuelve un parámetro de configuración
     * @param $param
     *
     * @return null
     */
    public function get($param)
    {
        return isset($this->config[$param]) ? $this->config[$param] : null;
    }

    /**
     * Método que devuelve toda la configuración en un array
     * @return mixed
     */
    public function dumpConfig()
    {
        return $this->config;
    }

    /**
     * Servicio que devuelve los parámetros de configuración de Propel para las BD
     * @return mixed
     */
    public function getPropelParams()
    {
        return json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'properties.json'), true);
    }

    /**
     * Método estático para la generación de directorios
     * @param $dir
     */
    public static function createDir($dir) {
        if (!file_exists($dir)) {
            if (@mkdir($dir, 0775, true) === false) {
                throw new \PSFS\base\exception\ConfigException("Can't create directory ".$dir);
            }
        }
    }
}
