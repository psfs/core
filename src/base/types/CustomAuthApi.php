<?php

namespace PSFS\base\types;

use PSFS\base\exception\ApiException;
use PSFS\base\Security;
use PSFS\base\types\traits\SecureTrait;

/**
 * Class CustomAuthApi
 * @package PSFS\base\types
 */
abstract class CustomAuthApi extends CustomApi
{
    use SecureTrait;
    public function init()
    {
        parent::init();
        if (!$this->isLogged()) {
            throw new ApiException(t('Resource not authorized'), 401);
        }
    }
}
