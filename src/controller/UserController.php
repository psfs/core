<?php

namespace PSFS\controller;

use PSFS\base\dto\DeleteUserRequestDto;
use PSFS\base\dto\ValidationContext;
use PSFS\base\config\AdminForm;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\ResponseHelper;
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
    private static function normalizeLocaleCode(string $locale): ?string
    {
        $value = trim(str_replace('-', '_', $locale));
        if ('' === $value) {
            return null;
        }
        if (preg_match('/^[a-z]{2}$/i', $value) === 1) {
            $lang = strtolower($value);
            return $lang === 'en' ? 'en_US' : $lang . '_' . strtoupper($lang);
        }
        if (preg_match('/^[a-z]{2}_[a-z]{2}$/i', $value) === 1) {
            [$lang, $region] = explode('_', $value, 2);
            return strtolower($lang) . '_' . strtoupper($region);
        }
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $value) === 1) {
            return $value;
        }
        return null;
    }

    private static function extractAllowedAdminLocales(): array
    {
        $configured = (string)\PSFS\base\config\Config::getParam('i18n.locales', 'en_US,es_ES');
        $allowed = [];
        foreach (explode(',', $configured) as $locale) {
            $normalized = self::normalizeLocaleCode($locale);
            if (null !== $normalized) {
                $allowed[] = $normalized;
            }
        }
        if (empty($allowed)) {
            $allowed = ['en_US', 'es_ES'];
        }
        return array_values(array_unique($allowed));
    }

    private static function resolveSwitchLocale(string $requestedLocale, string $defaultLocale): string
    {
        $normalized = self::normalizeLocaleCode($requestedLocale);
        $defaultNormalized = self::normalizeLocaleCode($defaultLocale) ?: 'en_US';
        if (null === $normalized || !I18nHelper::isValidLocale($normalized)) {
            return $defaultNormalized;
        }
        $allowed = self::extractAllowedAdminLocales();
        if (!in_array($normalized, $allowed, true)) {
            return $defaultNormalized;
        }
        return $normalized;
    }

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
     * Force admin locale for the current session and redirect back.
     * @GET
     * @route /admin/locale/{locale}
     * @visible false
     */
    #[HttpMethod('GET')]
    #[Route('/admin/locale/{locale}')]
    #[Visible(false)]
    public function switchAdminLocale(string $locale): string
    {
        $targetLocale = self::resolveSwitchLocale(
            $locale,
            (string)$this->config->get('default.language', 'en_US')
        );

        I18nHelper::setLocale($targetLocale, null, true);
        Security::getInstance()->updateSession();

        $referer = Request::header('Referer');
        $rootUrl = Request::getInstance()->getRootUrl();
        if (is_string($referer)
            && '' !== trim($referer)
            && ('' === $rootUrl || str_starts_with($referer, $rootUrl))) {
            ResponseHelper::setHeader('HTTP/1.1 302 Found');
            ResponseHelper::setHeader('Location: ' . $referer);
            return '';
        }

        $this->redirect('admin');
        return '';
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
        $request = Request::getInstance();
        $payload = $request->getRawData();
        if (empty($payload)) {
            // Legacy fallback for clients sending form-urlencoded payloads.
            $payload = $request->getData();
        }
        $requestDto = new DeleteUserRequestDto(false);
        $requestDto->fromArray($payload);
        $validation = $requestDto->checkValidations(new ValidationContext(
            $payload,
            [],
            true,
            true
        ));
        if (!$validation->isValid()) {
            throw new ApiException($validation->firstMessage(t('Invalid request payload')), 400);
        }
        $username = (string)$requestDto->user;
        Security::getInstance()->deleteUser($username);
        return $this->json('OK');
    }
}
