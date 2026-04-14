<?php

namespace PSFS\base\types;

use PSFS\base\exception\ApiException;
use PSFS\base\types\traits\LoggedGuardTrait;
use PSFS\base\types\traits\SecureTrait;

/**
 * @package PSFS\base\types
 */
abstract class CustomAuthApi extends CustomApi
{
    use SecureTrait;
    use LoggedGuardTrait;

    public function init()
    {
        parent::init();
        $this->assertAuthorizedUser();
    }

    protected function assertAuthorizedUser(): void
    {
        $this->ensureLoggedOrThrow(new ApiException(t('Resource not authorized'), 401));
    }
}
