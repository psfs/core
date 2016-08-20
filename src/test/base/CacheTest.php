<?php
namespace PSFS\Test\base;

use PSFS\base\Cache;
use PSFS\base\Security;

/**
 * Class CacheTest
 * @package PSFS\Test\base
 */
class CacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Function to test the instance of Cache class
     * @return Cache
     */
    private function getInstance()
    {
        $cache = Cache::getInstance();

        $this->assertNotNull($cache);
        $this->assertInstanceOf("\\PSFS\\base\\Cache", $cache, 'Instance different than expected');

        return $cache;
    }

    /**
     * Test for basic usage of cache
     */
    public function testCacheUse()
    {
        $cache = $this->getInstance();

        // Test data
        $data = [uniqid('test') => microtime()];
        $hash = sha1(microtime());

        // TXT cache test
        $cache->storeData('tests' . DIRECTORY_SEPARATOR . $hash, json_encode($data));
        $this->assertFileExists(CACHE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . $hash, 'Cache not stored!!');

        // Gather cache data from file without transformation
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 300, function(){});
        $this->assertEquals($cachedData, json_encode($data), 'Different data cached!');

        // Gather cache data from file with JSON transformation
        $cache->storeData('tests' . DIRECTORY_SEPARATOR . $hash, $data, Cache::JSONGZ);
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 300, function(){}, Cache::JSONGZ);
        $this->assertEquals($cachedData, $data, 'Error when try to gather cache with JSON transform');

        // Gather cache data from expired file
        sleep(2);
        $cachedData = $cache->readFromCache('tests' . DIRECTORY_SEPARATOR . $hash, 1, function() use ($data){
            return $data;
        }, Cache::JSONGZ);
        $this->assertEquals($cachedData, $data, 'Error when try to gather cache with JSON transform');

        @rmdir(CACHE_DIR . DIRECTORY_SEPARATOR . 'test');
    }

    /**
     * Test for specific cache functionality in requests
     */
    public function testCacheForRequests()
    {
        $session = Security::getInstance();
        $session->setSessionKey('__CACHE__', [
            'cache' => 1,
            'http' => 'localhost/',
            'slug' => 'test'
        ]);

        $hash = Cache::getInstance()->getRequestCacheHash();
        $this->assertNotNull($hash, 'Invalid cache hash');
        $this->assertEquals($hash, sha1('localhost/ test'), 'Different hash returned by cache');

        $this->assertTrue(false === Cache::needCache(), 'Test url expired or error checking cache');

        sleep(1);
        $this->assertTrue(false === Cache::needCache(), 'Need more time to check cache for this url');
    }
}