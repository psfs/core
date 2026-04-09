<?php

namespace PSFS\base\types;

use PSFS\base\Cache;
use PSFS\base\Logger;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\attributes\Injectable;

/**
 * @package PSFS\base\types
 */
abstract class SimpleService extends Singleton
{

    /**
     * @Injectable
     * @var \PSFS\base\Logger
     */
    #[Injectable(class: Logger::class)]
    protected Logger $log;
    /**
     * @Injectable
     * @var \PSFS\base\Cache
     */
    #[Injectable(class: Cache::class)]
    protected Cache $cache;

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
