<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Security;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Class CacheTest
 * @package PSFS\tests\base
 */
class CacheTest extends TestCase
{
    /**
     * Function to test the instance of Cache class
     * @return Cache
     * @throws GeneratorException
     */
    private function getInstance(): Cache
    {
        FileHelper::deleteDir(CACHE_DIR);
        GeneratorHelper::createDir(CACHE_DIR);
        Cache::dropInstance();
        $cache = Cache::getInstance();

        $this->assertNotNull($cache);
        $this->assertInstanceOf(Cache::class, $cache, 'Instance different from expected');

        return $cache;
    }

    /**
     * Test for basic usage of cache
     * @covers
     */
    public function testCacheUse()
    {
        $cache = $this->getInstance();

        // Test data
        $data = [uniqid('test', true) => microtime()];
        $hash = sha1(microtime());

        // TXT cache test
        $cache->storeData('tests' . DIRECTORY_SEPARATOR . $hash, json_encode($data));
        $this->assertFileExists(CACHE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . $hash, 'Cache not stored!!');

        // Gather cache data from file without transformation
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 300, function () {
        });
        $this->assertEquals($cachedData, json_encode($data), 'Different data cached!');

        // Gather cache data from file with JSON transformation
        $cache->storeData('tests' . DIRECTORY_SEPARATOR . $hash, $data, Cache::JSONGZ);
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 300, function () {
        }, Cache::JSONGZ);
        $this->assertEquals($cachedData, $data, 'Error when try to gather cache with JSON transform');

        // Gather cache data from expired file
        sleep(2);
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 1, function () use ($data) {
            return $data;
        }, Cache::JSONGZ);
        $this->assertEquals($cachedData, $data, 'Error when try to gather cache with JSON transform');

        FileHelper::deleteDir(CACHE_DIR . DIRECTORY_SEPARATOR . 'test');
    }

    private function prepareTestVariables(): array
    {
        $session = Security::getInstance();
        $hash = sha1(microtime());
        $session->setSessionKey('__CACHE__', [
            'cache' => 600,
            'params' => [],
            'http' => 'GET',
            'slug' => 'test-' . $hash,
            'class' => 'Test',
            'method' => 'test' . ucfirst($hash),
            'module' => 'TEST',
        ]);

        return Cache::getInstance()->getRequestCacheHash();
    }

    /**
     * Test for specific cache functionality in requests
     * @covers
     */
    public function testCacheForRequests()
    {
        list($path, $hash) = $this->prepareTestVariables();
        $this->assertNotNull($hash, 'Invalid cache hash');
        $this->assertNotEmpty($path, 'Invalid path to save the cache');

        $config = Config::getInstance()->dumpConfig();
        $cache_data_config = Config::getParam('cache.data.enable');
        Config::save([], [
            'label' => ['cache.data.enable'],
            'value' => [true]
        ]);
        Config::getInstance()->setDebugMode(false);
        $this->assertNotFalse(Cache::needCache(), 'Test url expired or error checking cache');

        // Flushing cache
        Cache::getInstance()->flushCache();
        $this->assertDirectoryDoesNotExist(CACHE_DIR . DIRECTORY_SEPARATOR . $path, 'Cache directory not deleted properly');

        $config['cache.data.enable'] = $cache_data_config;
        Config::save($config, []);

        // Cleaning test data
        FileHelper::deleteDir(CACHE_DIR . DIRECTORY_SEPARATOR . 'tests');
        $this->assertDirectoryDoesNotExist(CACHE_DIR . DIRECTORY_SEPARATOR . 'tests', 'Test data directory not cleaned properly');

    }

    /**
     * Test privileges in folder
     * @covers
     * @throws GeneratorException
     */
    public function testPrivileges()
    {
        list($path, $hash) = $this->prepareTestVariables();
        GeneratorHelper::createDir(dirname(CACHE_DIR . DIRECTORY_SEPARATOR . $path));
        GeneratorHelper::createDir(CACHE_DIR . DIRECTORY_SEPARATOR . $path);
        $cache = $this->getInstance();
        chmod(realpath(CACHE_DIR . DIRECTORY_SEPARATOR . $path), 0600);
        $cache->storeData($path . $hash, json_encode(''));
        chmod(realpath(CACHE_DIR . DIRECTORY_SEPARATOR . $path), 0777);
        FileHelper::deleteDir(CACHE_DIR . DIRECTORY_SEPARATOR . $path);
        chmod(realpath(CACHE_DIR . DIRECTORY_SEPARATOR . 'TEST'), 0777);
        FileHelper::deleteDir(CACHE_DIR . DIRECTORY_SEPARATOR . 'TEST');
    }
}