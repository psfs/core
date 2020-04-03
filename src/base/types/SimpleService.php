<?php
namespace PSFS\base\types;

use PSFS\base\Logger;
use PSFS\base\Singleton;

/**
 * Class SimpleService
 * @package PSFS\base\types
 */
abstract class SimpleService extends Singleton {

    /**
     * @Injectable
     * @var \PSFS\base\Logger Log de las llamadas
     */
    protected $log;
    /**
     * @Injectable
     * @var \PSFS\base\Cache $cache
     */
    protected $cache;

    /**
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @param Logger $log
     */
    public function setLog($log)
    {
        $this->log = $log;
    }
}
