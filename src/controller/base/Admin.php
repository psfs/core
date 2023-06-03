<?php

namespace PSFS\controller\base;

use PSFS\base\exception\UserAuthException;
use PSFS\base\types\AuthAdminController;
use PSFS\controller\UserController;
use PSFS\services\AdminServices;

/**
 * Class Admin
 * @package PSFS\controller
 * @domain ROOT
 */
abstract class Admin extends AuthAdminController
{
    const DOMAIN = 'ROOT';

    /**
     * @Injectable
     * @var \PSFS\base\config\Config Configuration service
     */
    protected $config;
    /**
     * @Injectable
     * @var \PSFS\services\AdminServices Admin service
     */
    protected $srv;

    /**
     * Método estático de login de administrador
     * @return string HTML
     * @throws UserAuthException
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function staticAdminLogon(): string
    {
        if (self::isTest()) {
            throw new UserAuthException();
        }
        if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json')) {
            return AdminServices::getInstance()->setAdminHeaders();
        } else {
            return UserController::showAdminManager();
        }
    }

    /**
     * Método que gestiona el menú de administración
     * @GET
     * @route /admin
     * @visible false
     * @return string|null
     */
    public function index()
    {
        return $this->render("index.html.twig");
    }

}
