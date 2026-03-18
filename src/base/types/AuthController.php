<?php

namespace PSFS\base\types;

use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\UserAuthException;
use PSFS\base\types\interfaces\AuthInterface;
use PSFS\base\types\traits\SecureTrait;

/**
 * @package PSFS\base\types
 */
abstract class AuthController extends Controller implements AuthInterface
{
    use SecureTrait;

    /**
     * @throws AccessDeniedException|UserAuthException
     */
    public function init()
    {
        parent::init();
        if (!$this->isLogged()) {
            throw new UserAuthException(t("User not logged in"));
        }
    }

}
