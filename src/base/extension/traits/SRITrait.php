<?php
namespace PSFS\base\extension\traits;

use PSFS\base\Cache;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\traits\SingletonTrait;

defined('CSS_SRI_FILENAME') or define('CSS_SRI_FILENAME', CACHE_DIR . DIRECTORY_SEPARATOR . 'css.sri.json');
defined('JS_SRI_FILENAME') or define('JS_SRI_FILENAME', CACHE_DIR . DIRECTORY_SEPARATOR . 'js.sri.json');
/**
 * Trait SRITrait
 * @package PSFS\base\extension\traits
 */
trait SRITrait {
    use SingletonTrait;

    /**
     * @var array
     */
    protected $sri = [];

    /**
     * @var string
     */
    protected $sriFilename;

    /**
     * @param string $type
     */
    public function init($type) {
        $this->sriFilename = $type === 'js' ? JS_SRI_FILENAME : CSS_SRI_FILENAME;
        /** @var Cache $cache */
        $cache = Cache::getInstance();
        $this->sri = $cache->getDataFromFile($this->sriFilename, Cache::JSON, true);
        if(empty($this->sri)) {
            $this->sri = [];
        }
    }

    /**
     * @param $hash
     * @param string $type
     * @return mixed|string
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function getSriHash($hash, $type = 'js') {
        if(array_key_exists($hash, $this->sri)) {
            $sriHash = $this->sri[$hash];
        } else {
            Inspector::stats('[SRITrait] Generating SRI for ' . $hash, Inspector::SCOPE_DEBUG);
            $filename = WEB_DIR . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $hash . '.' . $type;
            $sriHash = base64_encode(hash("sha384", file_get_contents($filename), true));
            $this->sri[$hash] = $sriHash;
            Cache::getInstance()->storeData($this->sriFilename, $this->sri, Cache::JSON, true);
        }
        return $sriHash;
    }
}