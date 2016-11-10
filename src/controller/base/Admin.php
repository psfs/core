<?php

namespace PSFS\controller\base;

use PSFS\base\config\Config;
use PSFS\base\config\LoginForm;
use PSFS\base\Router;
use PSFS\base\Template;
use PSFS\base\types\AuthAdminController;
use PSFS\services\AdminServices;

/**
 * Class Admin
 * @package PSFS\controller
 * @domain ROOT
 */
class Admin extends AuthAdminController{

    const DOMAIN = 'ROOT';

    /**
     * @Inyectable
     * @var \PSFS\base\config\Config Configuration service
     */
    protected $config;
    /**
     * @Inyectable
     * @var \PSFS\services\AdminServices Admin service
     */
    protected $srv;

    /**
     * Wrapper de asignación de los menus
     * @return array
     */
    protected function getMenu() {
        return Router::getInstance()->getAdminRoutes();
    }

    /**
     * Método estático de login de administrador
     * @param string $route
     * @return string HTML
     * @throws \PSFS\base\exception\FormException
     */
    public static function staticAdminLogon($route = null) {
        if('login' !== Config::getInstance()->get('admin_login')) {
            return AdminServices::getInstance()->setAdminHeaders();
        } else {
            $form = new LoginForm();
            $form->setData(array("route" => $route));
            $form->build();
            $tpl = Template::getInstance();
            $tpl->setPublicZone(true);
            return $tpl->render("login.html.twig", array(
                'form' => $form,
            ));
        }
    }

    /**
     * Método que gestiona el menú de administración
     * @GET
     * @route /admin
     * @visible false
     * @return string|null
     */
    public function index() {
        return $this->render("index.html.twig");
    }

}
