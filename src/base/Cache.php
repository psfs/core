<?php
namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\SingletonTrait;

/**
 * Class Cache
 * @package PSFS\base
 * Gestión de los ficheros de cache
 */
class Cache
{
    /**
     * @var \Memcache
     */
    protected $memcache = null;

    const JSON = 1;
    const TEXT = 2;
    const GZIP = 3;
    const JSONGZ = 4;
    const MEMCACHE = 5;

    use SingletonTrait;

    /**
     * @return bool
     */
    public static function canUseMemcache()
    {
        return Config::getParam('psfs.memcache', false) && !Config::getParam('debug') && class_exists('Memcached');
    }

    /**
     * Método que guarda un text en un fichero
     * @param string $data
     * @param string $path
     * @throws ConfigException
     */
    private function saveTextToFile($data, $path)
    {
        GeneratorHelper::createDir(dirname($path));
        if (false === file_put_contents($path, $data)) {
            throw new ConfigException(_('No se tienen los permisos suficientes para escribir en el fichero ') . $path);
        }
    }

    /**
     * Método que extrae el texto de un fichero
     * @param string $path
     * @param int $transform
     * @param boolean $absolute
     * @return mixed
     */
    public function getDataFromFile($path, $transform = Cache::TEXT, $absolute = false)
    {
        $data = null;
        $absolutePath = ($absolute) ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        if (file_exists($absolutePath)) {
            $data = file_get_contents($absolutePath);
        }
        return Cache::extractDataWithFormat($data, $transform);
    }

    /**
     * Método que verifica si un fichero tiene la cache expirada
     * @param string $path
     * @param int $expires
     * @param boolean $absolute
     * @return bool
     */
    private function hasExpiredCache($path, $expires = 300, $absolute = false)
    {
        $absolutePath = ($absolute) ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        $lasModificationDate = filemtime($absolutePath);
        return ($lasModificationDate + $expires <= time());
    }

    /**
     * Método que transforma los datos de salida
     * @param string $data
     * @param int $transform
     * @return array|string|null
     */
    public static function extractDataWithFormat($data, $transform = Cache::TEXT)
    {
        switch ($transform) {
            case Cache::JSON:
                $data = json_decode($data, true);
                break;
            case Cache::JSONGZ:
                $data = Cache::extractDataWithFormat($data, Cache::GZIP);
                $data = Cache::extractDataWithFormat($data, Cache::JSON);
                break;
            case Cache::GZIP:
                if (function_exists('gzuncompress') && !empty($data)) {
                    $data = @gzuncompress($data ?: '');
                }
                break;
        }
        return $data;
    }

    /**
     * Método que transforma los datos de entrada del fichero
     * @param string $data
     * @param int $transform
     * @return string
     */
    public static function transformData($data, $transform = Cache::TEXT)
    {
        switch ($transform) {
            case Cache::JSON:
                $data = json_encode($data, JSON_PRETTY_PRINT);
                break;
            case Cache::JSONGZ:
                $data = Cache::transformData($data, Cache::JSON);
                $data = Cache::transformData($data, Cache::GZIP);
                break;
            case Cache::GZIP:
                if (function_exists('gzcompress')) {
                    $data = gzcompress($data ?: '');
                }
                break;
        }
        return $data;
    }

    /**
     * Método que guarda en fichero los datos pasados
     * @param $path
     * @param $data
     * @param int $transform
     * @param boolean $absolute
     * @param integer $expires
     */
    public function storeData($path, $data, $transform = Cache::TEXT, $absolute = false, $expires = 600)
    {
        $data = Cache::transformData($data, $transform);
        $absolutePath = ($absolute) ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        $this->saveTextToFile($data, $absolutePath);
    }

    /**
     * Método que verifica si tiene que leer o no un fichero de cache
     * @param string $path
     * @param int $expires
     * @param callable $function
     * @param int $transform
     * @return mixed
     */
    public function readFromCache($path, $expires = 300, callable $function, $transform = Cache::TEXT)
    {
        $data = null;
        if (file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . $path)) {
            if (null !== $function && $this->hasExpiredCache($path, $expires)) {
                $data = call_user_func($function);
                $this->storeData($path, $data, $transform, false, $expires);
            } else {
                $data = $this->getDataFromFile($path, $transform);
            }
        }
        return $data;
    }

    /**
     * @return bool
     */
    private static function checkAdminSite()
    {
        $isAdminRequest = false;
        $lastRequest = Security::getInstance()->getSessionKey("lastRequest");
        if (null !== $lastRequest) {
            $url = str_replace(Request::getInstance()->getRootUrl(true), '', $lastRequest['url']);
            $isAdminRequest = preg_match('/^\/admin\//i', $url);
        }
        return (bool)$isAdminRequest;
    }

    /**
     * Método estático que revisa si se necesita cachear la respuesta de un servicio o no
     * @return integer|boolean
     */
    public static function needCache()
    {
        $needCache = false;
        if (!self::checkAdminSite()) {
            $action = Security::getInstance()->getSessionKey("__CACHE__");
            if (null !== $action && array_key_exists("cache", $action) && $action["cache"] > 0) {
                $needCache = $action["cache"];
            }
        }
        return $needCache;
    }

    /**
     * Método que construye un hash para almacenar la cache
     * @return string
     */
    public function getRequestCacheHash()
    {
        $hash = "";
        $action = Security::getInstance()->getSessionKey("__CACHE__");
        if (null !== $action && $action["cache"] > 0) {
            $hash = $action["http"] . " " . $action["slug"];
        }
        return sha1($hash);
    }
}
