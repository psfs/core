<?php

namespace PSFS\controller;

use PSFS\base\config\AdminForm;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\controller\base\Admin;
use PSFS\services\AdminServices;

/**
 * Class UserController
 * @package PSFS\controller
 */
class UserController extends Admin
{
    /**
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function showAdminManager()
    {
        if (Request::getInstance()->getMethod() != 'GET') {
            return self::updateAdminUsers();
        }
        $admins = AdminServices::getInstance()->getAdmins();
        $form = new AdminForm();
        $form->build();
        return Template::getInstance()->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
            'profiles' => Security::getProfiles(),
        ));
    }

    /**
     * Método que gestiona los usuarios administradores de la plataforma
     * @GET
     * @route /admin/setup
     * @icon fa-users
     * @label Gestor de usuarios PSFS
     * @return string|null
     */
    public function adminers()
    {
        return self::showAdminManager();
    }

    /**
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function updateAdminUsers()
    {
        $admins = AdminServices::getInstance()->getAdmins();
        $form = new AdminForm();
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            if (Security::save($form->getData())) {
                Logger::log('Configuration saved successful');
                Security::getInstance()->setFlash("callback_message", t("Usuario agregado correctamente"));
                Security::getInstance()->setFlash("callback_route", Router::getInstance()->getRoute("admin", true));
            } else {
                throw new ConfigException(t('Error al guardar los administradores, prueba a cambiar los permisos'));
            }
        }
        return Template::getInstance()->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
            'profiles' => Security::getProfiles(),
        ));
    }

    /**
     * Servicio que guarda los usuarios de administración
     * @POST
     * @route /admin/setup
     * @visible false
     * @return string|void
     */
    public function setAdminUsers()
    {
        return self::updateAdminUsers();
    }

    /**
     * Acción que pinta un formulario genérico de login pra la zona restringida
     * @GET
     * @route /admin/login
     * @visible false
     * @return string HTML
     */
    public function adminLogin()
    {
        if ($this->isAdmin()) {
            $this->redirect('admin');
        } else {
            return Admin::staticAdminLogon();
        }
    }
}
