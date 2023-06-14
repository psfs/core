<?php
namespace PSFS\base\types\helpers;

use Propel\Runtime\Connection\ConnectionWrapper;

class PdoHelper extends ConnectionWrapper {
    /**
     * SQL code of the latest performed query.
     *
     * @var string
     */
    protected $lastExecutedQuery = '';
}