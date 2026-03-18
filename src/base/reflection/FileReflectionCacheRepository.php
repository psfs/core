<?php

namespace PSFS\base\reflection;

use PSFS\base\Cache;
use PSFS\base\Logger;
use ReflectionClass;

class FileReflectionCacheRepository implements ReflectionCacheRepositoryInterface
{
    protected string $className;
    protected string $cacheFilename;
    protected string $signatureFilename;
    protected Cache $cache;

    public function __construct(string $className, ?Cache $cache = null)
    {
        $this->className = ltrim($className, '\\');
        $this->cacheFilename = $this->buildCacheFilename($this->className);
        $this->signatureFilename = $this->cacheFilename . '.sig';
        $this->cache = $cache ?? Cache::getInstance();
    }

    public function read(): array
    {
        $stored = $this->cache->getDataFromFile($this->cacheFilename, Cache::JSON);
        if (!is_array($stored)) {
            return [];
        }
        $storedSignature = $this->cache->getDataFromFile($this->signatureFilename, Cache::TEXT);
        if (is_string($storedSignature) && '' !== trim($storedSignature)) {
            $currentSignature = $this->getSourceSignature();
            if (trim($storedSignature) !== $currentSignature) {
                $this->invalidate();
                return [];
            }
        }
        return $stored;
    }

    public function save(array $properties): bool
    {
        try {
            $this->cache->storeData($this->cacheFilename, $properties, Cache::JSON);
            $this->cache->storeData($this->signatureFilename, $this->getSourceSignature(), Cache::TEXT);
            return true;
        } catch (\Throwable $exception) {
            Logger::log('[Reflections][File] ' . $exception->getMessage(), LOG_WARNING);
            return false;
        }
    }

    public function refresh(): array
    {
        $this->invalidate();
        return $this->read();
    }

    public function invalidate(): void
    {
        $cacheFile = $this->getCachePath();
        $signatureFile = $this->getSignaturePath();
        if (file_exists($cacheFile) && @unlink($cacheFile) === false) {
            Logger::log('[FileReflectionCacheRepositori::invalidate] Failed to delete cache file: ' . $cacheFile, LOG_WARNING);
        }
        if (file_exists($signatureFile) && @unlink($signatureFile) === false) {
            Logger::log('[FileReflectionCacheRepositori::invalidate] Failed to delete signature file: ' . $signatureFile, LOG_WARNING);
        }
    }

    public function getCachePath(): string
    {
        return CACHE_DIR . DIRECTORY_SEPARATOR . $this->cacheFilename;
    }

    public function getSignaturePath(): string
    {
        return CACHE_DIR . DIRECTORY_SEPARATOR . $this->signatureFilename;
    }

    public function getSourceSignature(): string
    {
        $file = null;
        if (class_exists($this->className)) {
            $reflection = new ReflectionClass($this->className);
            $file = $reflection->getFileName();
        }
        if (is_string($file) && file_exists($file)) {
            return (string)filemtime($file) . ':' . sha1_file($file);
        }
        return 'class:' . sha1($this->className);
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    private function buildCacheFilename(string $className): string
    {
        $hash = sha1($className);
        return 'reflections' . DIRECTORY_SEPARATOR . substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2) . DIRECTORY_SEPARATOR . $hash . '.json';
    }
}
