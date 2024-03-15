<?php

namespace PSFS\base\types;

use PSFS\base\exception\ApiException;
use PSFS\base\Security;

/**
 * Class CustomAuthApi
 * @package PSFS\base\types
 */
abstract class CustomAuthApi extends CustomApi
{

    public function init()
    {
        if (!Security::getInstance()->isLogged()) {
            throw new ApiException(t('Resource not authorized'), 401);
        }
        parent::init();
    }
}
