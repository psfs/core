<?php

namespace PSFS\base\config;


use PSFS\base\Cache;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\types\SingletonTrait;

/**
 * Class Config
 * @package PSFS\base\config
 */
class Config {

    use SingletonTrait;
    const DEFAULT_LANGUAGE = "es";
    const DEFAULT_ENCODE = "UTF-8";
    const DEFAULT_CTYPE = "text/html";
    const DEFAULT_DATETIMEZONE = "Europe/Madrid";

    protected $config = array();
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
     * Constructor Config
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Método que carga la configuración del sistema
     * @return Config
     */
    protected function init()
    {
        if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json"))
        {
            $this->config = Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json", Cache::JSON, true) ?: array();
            $this->debug = (array_key_exists('debug', $this->config)) ? (bool)$this->config['debug'] : false;
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
    public function getTemplatePath() {
        $path = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR;
        return realpath($path);
    }

    /**
     * Método que indica si se ha configurado correctamente la plataforma
     * @return bool
     */
    public function isConfigured()
    {
        $configured = true;
        foreach (static::$required as $required) {
            if (!array_key_exists($required, $this->config)) {
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
     * @return boolean
     */
    public static function save(array $data, array $extra = null)
    {
        //En caso de tener parámetros nuevos los guardamos
        if (!empty($extra['label'])) {
            foreach ($extra['label'] as $index => $field) {
                if (array_key_exists($index, $extra['value']) && !empty($extra['value'][$index])) {
                    /** @var $data array */
                    $data[$field] = $extra['value'][$index];
                }
            }
        }
        $final_data = array();
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                if (null !== $value || $value !== '') {
                    $final_data[$key] = $value;
                }
            }
        }
        $saved = false;
        try {
            Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json", $final_data, Cache::JSON, true);
            $saved = true;
        } catch (ConfigException $e) {
            Logger::getInstance()->errorLog($e->getMessage());
        }
        return $saved;
    }

    /**
     * Método que devuelve un parámetro de configuración
     * @param $param
     *
     * @return null
     */
    public function get($param) {
        return array_key_exists($param, $this->config) ? $this->config[$param] : null;
    }

    /**
     * Método que devuelve toda la configuración en un array
     * @return mixed
     */
    public function dumpConfig() {
        return $this->config;
    }

    /**
     * Servicio que devuelve los parámetros de configuración de Propel para las BD
     * @return mixed
     */
    public function getPropelParams() {
        return Cache::getInstance()->getDataFromFile(__DIR__.DIRECTORY_SEPARATOR.'properties.json', Cache::JSON, true);
    }

    /**
     * Método estático para la generación de directorios
     * @param $dir
     * throws ConfigException
     */
    public static function createDir($dir) {
        if (!file_exists($dir) && @mkdir($dir, 0775, true) === false ) {
            throw new ConfigException(_('Can\'t create directory ') . $dir);
        }
    }
}
