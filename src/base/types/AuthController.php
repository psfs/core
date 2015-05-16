<?php

namespace PSFS\base\types;


use PSFS\base\types\interfaces\AuthInterface;
use PSFS\base\Security;

/**
 * Class AuthController
 * @package PSFS\base\types
 */
abstract class AuthController extends Controller implements AuthInterface{

    protected $security;

    /**
     * Constructor por defecto
     */
    public function __construct()
    {
        parent::__construct();
        $this->security = Security::getInstance();
    }

    /**
     * Método que verifica si está autenticado el usuario
     */
    public function isLogged()
    {
        return (null !== $this->security->getUser());
    }

    /**
     * Método que devuelve si un usuario es administrador de la plataforma
     * @return bool
     */
    public function isAdmin()
    {
        $is_admin = false;
        return $is_admin;
    }

    /**
     * Método que define si un usuario puede realizar una acción concreta
     * @param $action
     *
     * @return bool
     */
    public function canDo($action)
    {
        return true;
    }
}