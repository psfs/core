<?php

namespace PSFS\base\types\traits;

use PSFS\base\Security;

/**
 * Class AuthController
 * @package PSFS\base\types
 */
trait SecureTrait
{
    use BoostrapTrait;

    /**
     * Método que verifica si está autenticado el usuario
     * @return boolean
     */
    public function isLogged()
    {
        return (null !== Security::getInstance()->getUser() || $this->isAdmin());
    }

    /**
     * Método que devuelve si un usuario es administrador de la plataforma
     * @return boolean
     */
    public function isAdmin()
    {
        return Security::getInstance()->canAccessRestrictedAdmin();
    }

    /**
     * Método que define si un usuario puede realizar una acción concreta
     * @param $action
     * TODO
     * @return bool
     */
    public function canDo($action)
    {
        return true;
    }


}
