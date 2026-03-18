<?php

namespace PSFS\base\types\helpers;

use Propel\Runtime\Connection\ConnectionWrapper;

class PdoHelper extends ConnectionWrapper
{
    /**
     *
     * @var string
     */
    protected $lastExecutedQuery = '';
}
