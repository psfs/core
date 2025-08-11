<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\traits\SingletonTrait;

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
    const REDIS = 6;

    const CACHE_SESSION_VAR = '__CACHE__';

    use SingletonTrait;

    public function __construct()
    {
        $this->setLoaded(true);
    }

    /**
     * @return bool
     */
    public static function canUseMemcache()
    {
        return Config::getParam('psfs.memcache', false) && !Config::getParam('debug') && class_exists('Memcached');
    }

    /**
     * @return bool
     */
    public static function canUseRedis()
    {
        return Config::getParam('psfs.redis', false) && !Config::getParam('debug') && class_exists('Redis');
    }

    /**
     * @param string $data
     * @param string $path
     * @throws GeneratorException
     * @throws ConfigException
     */
    private function saveTextToFile($data, $path)
    {
        GeneratorHelper::createDir(dirname($path));
        if (false === FileHelper::writeFile($path, $data)) {
            throw new ConfigException(t('No se tienen los permisos suficientes para escribir en el fichero ') . $path);
        }
    }

    /**
     * @param string $path
     * @param int $transform
     * @param boolean $absolute
     * @return mixed
     */
    public function getDataFromFile($path, $transform = Cache::TEXT, $absolute = false)
    {
        Inspector::stats('[Cache] Gathering data from cache', Inspector::SCOPE_DEBUG);
        $data = null;
        $absolutePath = $absolute ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        if (file_exists($absolutePath)) {
            $data = FileHelper::readFile($absolutePath);
        }
        return self::extractDataWithFormat($data, $transform);
    }

    /**
     * @param string $path
     * @param int $expires
     * @param boolean $absolute
     * @return bool
     */
    private function hasExpiredCache($path, $expires = 300, $absolute = false)
    {
        Inspector::stats('[Cache] Checking expiration', Inspector::SCOPE_DEBUG);
        $absolutePath = ($absolute) ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        $lasModificationDate = filemtime($absolutePath);
        return ($lasModificationDate + $expires <= time());
    }

    /**
     * @param mixed $data
     * @param int $transform
     * @return mixed
     */
    public static function extractDataWithFormat($data = null, $transform = Cache::TEXT)
    {
        Inspector::stats('[Cache] Extracting data from cache', Inspector::SCOPE_DEBUG);
        switch ($transform) {
            case self::JSON:
                $data = json_decode($data ?: '', true);
                break;
            case self::JSONGZ:
                $data = self::extractDataWithFormat($data, self::GZIP);
                $data = self::extractDataWithFormat($data, self::JSON);
                break;
            case self::GZIP:
                if (null !== $data && function_exists('gzuncompress')) {
                    $data = @gzuncompress($data ?: '');
                }
                break;
        }
        return $data;
    }

    /**
     * @param string $data
     * @param int $transform
     * @return string
     */
    public static function transformData($data, $transform = Cache::TEXT)
    {
        Logger::log('Transform data in cache', LOG_DEBUG);
        switch ($transform) {
            case self::JSON:
                $data = json_encode($data, JSON_PRETTY_PRINT);
                break;
            case self::JSONGZ:
                $data = self::transformData($data, self::JSON);
                $data = self::transformData($data, self::GZIP);
                break;
            case self::GZIP:
                if (function_exists('gzcompress')) {
                    $data = gzcompress($data ?: '');
                }
                break;
        }
        return $data;
    }

    /**
     * @param string $path
     * @param mixed $data
     * @param int $transform
     * @param bool $absolute
     * @throws GeneratorException
     * @throws ConfigException
     */
    public function storeData($path, $data, $transform = Cache::TEXT, $absolute = false)
    {
        Inspector::stats('[Cache] Store data in cache', Inspector::SCOPE_DEBUG);
        $data = self::transformData($data, $transform);
        $absolutePath = $absolute ? $path : CACHE_DIR . DIRECTORY_SEPARATOR . $path;
        $this->saveTextToFile($data, $absolutePath);
    }

    /**
     * @param string $path
     * @param int $expires
     * @param callable $function
     * @param int $transform
     * @param bool $ignoreExpiration
     * @return mixed|null
     * @throws GeneratorException
     * @throws ConfigException
     */
    public function readFromCache($path, $expires = 300, $function = null, $transform = Cache::TEXT, $ignoreExpiration = false)
    {
        $data = null;
        Inspector::stats('[Cache] Reading data from cache: ' . json_encode(['path' => $path]), Inspector::SCOPE_DEBUG);
        if (file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . $path)) {
            if (is_callable($function) && $this->hasExpiredCache($path, $expires) && !$ignoreExpiration) {
                $data = $function();
                $this->storeData($path, $data, $transform);
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
        return Security::getInstance()->canAccessRestrictedAdmin();
    }

    /**
     * @return integer|boolean
     */
    public static function needCache()
    {
        $needCache = false;
        Logger::log('Checking cache requirements');
        if (!self::checkAdminSite() && !Config::getParam('debug') && Config::getParam('cache.data.enable', false)) {
            $action = Security::getInstance()->getSessionKey(self::CACHE_SESSION_VAR);
            Logger::log('Gathering cache params from Session', LOG_DEBUG, $action);
            if (null !== $action && array_key_exists('cache', $action) && $action['cache'] > 0) {
                $needCache = $action['cache'];
            }
        }
        return $needCache;
    }

    /**
     * @return array
     */
    public function getRequestCacheHash()
    {
        $hashPath = null;
        $filename = null;
        $action = Security::getInstance()->getSessionKey(self::CACHE_SESSION_VAR);
        Inspector::stats('[Cache] Gathering cache hash for request', Inspector::SCOPE_DEBUG);
        if (null !== $action && $action['cache'] > 0) {
            $query = $action['params'];
            $query[Api::HEADER_API_LANG] = Request::header(Api::HEADER_API_LANG, 'es');
            $filename = FileHelper::generateHashFilename($action['http'], $action['slug'], $query);
            $hashPath = FileHelper::generateCachePath($action, $query);
            Inspector::stats('[Cache] Cache file calculated: ' . json_encode(['file' => $filename, 'hash' => $hashPath]), Inspector::SCOPE_DEBUG);
            Logger::log('Cache file calculated', LOG_DEBUG, ['file' => $filename, 'hash' => $hashPath]);
        }
        return [$hashPath, $filename];
    }

    public function flushCache()
    {
        if (Config::getParam('cache.data.enable', false)) {
            Inspector::stats('[Cache] Flushing cache', Inspector::SCOPE_DEBUG);
            $action = Security::getInstance()->getSessionKey(self::CACHE_SESSION_VAR);
            if (is_array($action)) {
                $hashPath = FileHelper::generateCachePath($action, $action['params']) . '..' . DIRECTORY_SEPARATOR . ' .. ' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
                if (!file_exists($hashPath)) {
                    $hashPath = CACHE_DIR . DIRECTORY_SEPARATOR . $hashPath;
                }
                FileHelper::deleteDir($hashPath);
            }
        }
    }
}
