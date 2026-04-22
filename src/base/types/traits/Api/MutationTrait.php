<?php

namespace PSFS\base\types\traits\Api;

use Propel\Runtime\Map\TableMap;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Header;
use PSFS\base\types\helpers\attributes\Label;

/**
 * @package PSFS\base\types\traits\Api
 */
trait MutationTrait
{
    use MutationI18nTrait;
    use MutationTableMapTrait;
    use MutationExtraColumnsTrait;
    use MutationRequestHydrationTrait;

    #[Header(Api::HEADER_API_LANG)]
    #[Label('Locale for the API request')]
    #[DefaultValue('es')]
    protected $lang;

    #[Header(Api::HEADER_API_FIELDTYPE)]
    #[Label('Field type for API Dto')]
    #[DefaultValue('phpName')]
    protected $fieldType = TableMap::TYPE_PHPNAME;

    /**
     * @var array
     */
    protected $extraColumns = array();

    /**
     * @var array
     */
    protected $query = array();

    /**
     * @var array
     */
    protected $data = array();

    /**
     * Number of items persisted successfully in a bulk save operation.
     *
     * @var int
     */
    protected int $bulkSavedCount = 0;

    /**
     * @return TableMap
     */
    abstract function getModelTableMap();

}
