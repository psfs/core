<?php

namespace PSFS\base\types;


use PSFS\base\exception\AccessDeniedException;
use PSFS\base\types\interfaces\AuthInterface;

/**
 * Class AuthController
 * @package PSFS\base\types
 */
abstract class AuthController extends Controller implements AuthInterface {

    /**
     * @Inyectable
     * @var \PSFS\base\Security Seguridad del controlador
     */
    protected $security;

    /**
     * Constructor por defecto
     * @throws AccessDeniedException
     */
    public function __construct() {
        $this->init();
        if (!$this->isLogged()) {
            throw new AccessDeniedException(_("User not logged in"));
        }
    }

    /**
     * Método que verifica si está autenticado el usuario
     * @return boolean
     */
    public function isLogged() {
        return (null !== $this->security->getUser());
    }

    /**
     * Método que devuelve si un usuario es administrador de la plataforma
     * @return boolean
     */
    public function isAdmin() {
        return (null !== $this->security->canAccessRestrictedAdmin());
    }

    /**
     * Método que define si un usuario puede realizar una acción concreta
     * @param $action
     * TODO
     * @return bool
     */
    public function canDo($action) {
        return true;
    }
}
