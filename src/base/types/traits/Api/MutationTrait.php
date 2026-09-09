<?php

namespace PSFS\base\types\traits\Api;

/**
 * @package PSFS\base\types\traits\Api
 */
trait MutationTrait
{
    use MutationI18nTrait;
    use MutationTableMapTrait;
    use MutationExtraColumnsTrait;
    use MutationRequestHydrationTrait;

    /** Contract implemented by generated API classes and custom API stubs. */
    abstract protected function getModelTableMap();

}
