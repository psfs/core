<?php

namespace PSFS\controller;

use PSFS\base\config\AdminForm;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Visible;
use PSFS\base\types\traits\Security\ProfileTrait;
use PSFS\controller\base\Admin;
use PSFS\services\AdminServices;

/**
 * Class UserController
 * @package PSFS\controller
 */
class UserController extends Admin
{
    private static function assertSuperAdminUserWriteAccess(): void
    {
        $security = Security::getInstance();
        $hasAdmins = count($security->getAdmins()) > 0;
        if ($hasAdmins && !$security->isSuperAdmin() && !Security::isTest()) {
            throw new ApiException(t('Restricted area'), 403);
        }
    }

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
     * Method that manages platform administrators
     * @GET
     * @route /admin/setup
     * @icon fa-users
     * @label PSFS user manager
     * @return string|null
     */
    #[HttpMethod('GET')]
    #[Route('/admin/setup')]
    #[Icon('fa-users')]
    #[Label('PSFS user manager')]
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
        self::assertSuperAdminUserWriteAccess();
        $admins = AdminServices::getInstance()->getAdmins();
        $form = new AdminForm();
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            if (Security::save($form->getData())) {
                Logger::log('Configuration saved successful');
                Security::getInstance()->setFlash("callback_message", t("User created successfully"));
                Security::getInstance()->setFlash("callback_route", Router::getInstance()->getRoute("admin", true));
            } else {
                throw new ConfigException(t('Error while saving administrators, please verify filesystem permissions'));
            }
        }
        return Template::getInstance()->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
            'profiles' => Security::getProfiles(),
        ));
    }

    /**
     * Service that stores administrator users
     * @POST
     * @route /admin/setup
     * @visible false
     * @return string|void
     */
    #[HttpMethod('POST')]
    #[Route('/admin/setup')]
    #[Visible(false)]
    public function setAdminUsers()
    {
        return self::updateAdminUsers();
    }

    /**
     * Action that renders a generic login form for the restricted area
     * @GET
     * @route /admin/login
     * @visible false
     * @return string HTML
     */
    #[HttpMethod('GET')]
    #[Route('/admin/login')]
    #[Visible(false)]
    public function adminLogin()
    {
        if ($this->isAdmin()) {
            $this->redirect('admin');
        } else {
            return Admin::staticAdminLogon();
        }
    }

    /**
     * Force session reset and Basic re-authentication challenge.
     * @GET
     * @route /admin/switch-user
     * @visible false
     */
    #[HttpMethod('GET')]
    #[Route('/admin/switch-user')]
    #[Visible(false)]
    public function switchUser()
    {
        return $this->srv->switchUser();
    }

    /**
     * Delete PSFS admin users
     * @PUT
     * @route /admin/setup
     */
    #[HttpMethod('PUT')]
    #[Route('/admin/setup')]
    public function deleteUsers()
    {
        self::assertSuperAdminUserWriteAccess();
        $data = Request::getInstance()->getData();
        $username = $data['user'] ?? null;
        if (empty($username)) {
            throw new ApiException(t('No user was provided to delete'), 400);
        }
        Security::getInstance()->deleteUser($username);
        return $this->json('OK');
    }
}
