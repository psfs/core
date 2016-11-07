<?php

namespace PSFS\base\config;


use PSFS\base\Cache;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\SingletonTrait;

/**
 * Class Config
 * @package PSFS\base\config
 */
class Config
{
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
    static public $optional = array('platform_name', 'debug', 'restricted', 'admin_login', 'logger.phpFire', 'logger.memory', 'poweredBy', 'author', 'author_email', 'version', 'front.version', 'cors.enabled', 'pagination.limit', 'api.secret');
    protected $debug = false;

    /**
     * Config Constructor
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Method that load the configuration data into the system
     * @return Config
     */
    protected function init()
    {
        if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json")) {
            $this->loadConfigData();
        }
        return $this;
    }

    /**
     * Method that saves the configuration
     * @param array $data
     * @param array $extra
     * @return array
     */
    protected static function saveConfigParams(array $data, array $extra)
    {
        Logger::log('Saving required config parameters');
        //En caso de tener parámetros nuevos los guardamos
        if (array_key_exists('label', $extra) && is_array($extra['label'])) {
            foreach ($extra['label'] as $index => $field) {
                if (array_key_exists($index, $extra['value']) && !empty($extra['value'][$index])) {
                    /** @var $data array */
                    $data[$field] = $extra['value'][$index];
                }
            }
        }
        return $data;
    }

    /**
     * Method that saves the extra parameters into the configuration
     * @param array $data
     * @return array
     */
    protected static function saveExtraParams(array $data)
    {
        $final_data = array();
        if (count($data) > 0) {
            Logger::log('Saving extra configuration parameters');
            foreach ($data as $key => $value) {
                if (null !== $value || $value !== '') {
                    $final_data[$key] = $value;
                }
            }
        }
        return $final_data;
    }

    /**
     * Method that returns if the system is in debug mode
     * @return boolean
     */
    public function getDebugMode()
    {
        return $this->debug;
    }

    /**
     * Method that returns the cache path
     * @return string
     */
    public function getCachePath()
    {
        return CACHE_DIR;
    }

    /**
     * Method that returns the templates path
     * @return string
     */
    public function getTemplatePath()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        return realpath($path);
    }

    /**
     * Method that checks if the platform is proper configured
     * @return boolean
     */
    public function isConfigured()
    {
        Logger::log('Checking configuration');
        $configured = (count($this->config) > 0);
        if ($configured) {
            foreach (static::$required as $required) {
                if (!array_key_exists($required, $this->config)) {
                    $configured = false;
                    break;
                }
            }
        }
        return ($configured || $this->checkTryToSaveConfig());
    }

    /**
     * Method that check if the user is trying to save the config
     * @return bool
     */
    public function checkTryToSaveConfig()
    {
        $uri = Request::getInstance()->getRequestUri();
        $method = Request::getInstance()->getMethod();
        return (preg_match('/^\/admin\/(config|setup)$/', $uri) !== false && strtoupper($method) === 'POST');
    }

    /**
     * Method that saves all the configuration in the system
     *
     * @param array $data
     * @param array|null $extra
     * @return boolean
     */
    public static function save(array $data, array $extra = null)
    {
        $data = self::saveConfigParams($data, $extra);
        $final_data = self::saveExtraParams($data);
        $saved = false;
        try {
            Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json", $final_data, Cache::JSON, true);
            Config::getInstance()->loadConfigData();
            $saved = true;
        } catch (ConfigException $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
        return $saved;
    }

    /**
     * Method that returns a config value
     * @param string $param
     *
     * @return mixed|null
     */
    public function get($param)
    {
        return array_key_exists($param, $this->config) ? $this->config[$param] : null;
    }

    /**
     * Method that returns all the configuration
     * @return array
     */
    public function dumpConfig()
    {
        return $this->config ?: [];
    }

    /**
     * Method that returns the Propel ORM parameters to setup the models
     * @return array|null
     */
    public function getPropelParams()
    {
        return Cache::getInstance()->getDataFromFile(__DIR__ . DIRECTORY_SEPARATOR . 'properties.json', Cache::JSON, true);
    }

    /**
     * Method that creates any parametrized path
     * @param string $dir
     * throws ConfigException
     */
    public static function createDir($dir)
    {
        try {
            if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                throw new \Exception(_('Can\'t create directory ') . $dir);
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
            if (!file_exists(dirname($dir))) {
                throw new ConfigException($e->getMessage() . $dir);
            }
        }
    }

    /**
     * Method that remove all data in the document root path
     */
    public static function clearDocumentRoot()
    {
        $rootDirs = array("css", "js", "media", "font");
        foreach ($rootDirs as $dir) {
            if (file_exists(WEB_DIR . DIRECTORY_SEPARATOR . $dir)) {
                try {
                    @shell_exec("rm -rf " . WEB_DIR . DIRECTORY_SEPARATOR . $dir);
                } catch (\Exception $e) {
                    Logger::log($e->getMessage());
                }
            }
        }
    }

    /**
     * Method that reloads config file
     */
    public function loadConfigData()
    {
        $this->config = Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "config.json",
            Cache::JSON,
            TRUE) ?: [];
        $this->debug = (array_key_exists('debug', $this->config)) ? (bool)$this->config['debug'] : FALSE;
    }

    /**
     * Clear configuration set
     */
    public function clearConfig()
    {
        $this->config = [];
    }
}
