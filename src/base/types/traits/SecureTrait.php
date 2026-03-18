<?php

namespace PSFS\base\types\traits;

use PSFS\base\Security;

/**
 * @package PSFS\base\types
 */
trait SecureTrait
{
    use BoostrapTrait;

    /**
     * @return boolean
     */
    public function isLogged()
    {
        return (null !== Security::getInstance()->getUser() || $this->isAdmin());
    }

    /**
     * @return boolean
     */
    public function isAdmin()
    {
        return Security::getInstance()->canAccessRestrictedAdmin();
    }

    /**
     * @param $action
     * @return bool
     */
    public function canDo($action)
    {
        return true;
    }


}
