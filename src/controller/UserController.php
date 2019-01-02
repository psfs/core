<?php
namespace PSFS\controller;

use PSFS\base\config\AdminForm;
use PSFS\base\config\LoginForm;
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
     * @label Gestor de usuarios PSFS
     * @return string|null
     * @throws \HttpException
     */
    public function adminers()
    {
        return self::showAdminManager();
    }

    /**
     * @return string
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
     * @throws \HttpException
     */
    public function setAdminUsers()
    {
        return self::updateAdminUsers();
    }

    /**
     * Acción que pinta un formulario genérico de login pra la zona restringida
     * @param string $route
     * @GET
     * @route /admin/login
     * @visible false
     * @return string HTML
     */
    public function adminLogin($route = null)
    {
        if ($this->isAdmin()) {
            return $this->redirect('admin');
        } else {
            return Admin::staticAdminLogon($route);
        }
    }

    /**
     * Servicio que valida el login
     * @param null $route
     * @POST
     * @visible false
     * @route /admin/login
     * @return string
     * @throws \PSFS\base\exception\FormException
     */
    public function postLogin($route = null)
    {
        $form = new LoginForm();
        $form->setData(array("route" => $route));
        $form->build();
        $tpl = Template::getInstance();
        $tpl->setPublicZone(true);
        $template = "login.html.twig";
        $params = array(
            'form' => $form,
        );
        $cookies = array();
        $form->hydrate();
        if ($form->isValid()) {
            if (Security::getInstance()->checkAdmin($form->getFieldValue("user"), $form->getFieldValue("pass"))) {
                $cookies = array(
                    array(
                        "name" => Security::getInstance()->getHash(),
                        "value" => base64_encode($form->getFieldValue("user") . ":" . $form->getFieldValue("pass")),
                        "expire" => time() + 3600,
                        "http" => true,
                    )
                );
                $template = "redirect.html.twig";
                $params = array(
                    'route' => $form->getFieldValue("route"),
                    'status_message' => t("Acceso permitido... redirigiendo!!"),
                    'delay' => 1,
                );
            } else {
                $form->setError("user", t("El usuario no tiene acceso a la web"));
            }
        }
        return $tpl->render($template, $params, $cookies);
    }
}