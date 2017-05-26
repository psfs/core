<?php
namespace PSFS\base\types\traits\Api;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use PSFS\base\Logger;

/**
 * Trait ConnectionTrait
 * @package PSFS\base\types\traits\Api
 */
trait ConnectionTrait {

    /**
     * @var ConnectionInterface con
     */
    protected $con = null;

    /**
     * @var bool
     */
    protected $debug = true;

    /**
     * Initialize db connection
     * @param TableMap $tableMap
     */
    protected function createConnection(TableMap $tableMap)
    {
        $this->con = Propel::getConnection($tableMap::DATABASE_NAME);
        if(method_exists($this->con, 'useDebug')) {
            Logger::log('Enabling debug queries mode', LOG_INFO);
            $this->con->useDebug($this->debug);
        }
    }

    /**
     * Close transactions if are requireds
     *
     * @param int $status
     */
    protected function closeTransaction($status)
    {
        $this->traceDebugQuery();
        if (null !== $this->con && $this->con->inTransaction()) {
            if ($status === 200) {
                $this->con->commit();
            } else {
                $this->con->rollBack();
            }
        }
        Propel::closeConnections();
    }

    /**
     * Trace debug query
     */
    protected function traceDebugQuery()
    {
        if ($this->debug) {
            Logger::getInstance(get_class($this))->debugLog($this->con->getLastExecutedQuery());
        }
    }
}