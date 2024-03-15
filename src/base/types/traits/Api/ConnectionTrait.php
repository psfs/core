<?php

namespace PSFS\base\types\traits\Api;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\traits\DebugTrait;

/**
 * Trait ConnectionTrait
 * @package PSFS\base\types\traits\Api
 */
trait ConnectionTrait
{

    /**
     * @var ConnectionInterface con
     */
    protected $con = null;

    /**
     * @var int
     */
    protected $items = 0;

    /**
     * Initialize db connection
     * @param TableMap $tableMap
     */
    protected function createConnection(TableMap $tableMap)
    {
        $this->con = Propel::getConnection($tableMap::DATABASE_NAME);
        $this->con->beginTransaction();
        if (method_exists($this->con, 'useDebug')) {
            Logger::log('Enabling debug queries mode', LOG_INFO);
            $this->con->useDebug(Config::getParam('debug'));
        }
    }

    /**
     * Close transactions if are requireds
     *
     * @param int $status
     */
    protected function closeTransaction($status)
    {
        if (null !== $this->con) {
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
    }

    /**
     * Trace debug query
     */
    protected function traceDebugQuery()
    {
        if (Config::getParam('debug')) {
            Logger::log($this->con->getLastExecutedQuery() ?: 'Empty Query', LOG_DEBUG);
        }
    }

    /**
     * Checks if the connection has a transaction initialized
     */
    protected function checkTransaction()
    {
        if (null !== $this->con && !$this->con->inTransaction()) {
            $this->con->beginTransaction();
        }
        if (null !== $this->con && $this->con->inTransaction()) {
            $this->items++;
        }
        if ($this->items >= Config::getParam('api.block.limit', 1000)) {
            $this->con->commit();
            $this->items = 0;
        }
    }
}
