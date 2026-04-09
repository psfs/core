<?php

namespace PSFS\controller\base;

use PSFS\base\exception\UserAuthException;
use PSFS\base\types\AuthAdminController;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\config\Config;
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
    #[Injectable(class: Config::class)]
    protected Config $config;
    /**
     * @Injectable
     * @var \PSFS\services\AdminServices Admin service
     */
    #[Injectable(class: AdminServices::class)]
    protected AdminServices $srv;

    /**
     * Static administrator login method
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
     * Method that manages the administration menu
     * @GET
     * @route /admin
     * @visible false
     * @return string|null
     */
    #[HttpMethod('GET')]
    #[Route('/admin')]
    #[Visible(false)]
    public function index()
    {
        return $this->render("index.html.twig");
    }

}
