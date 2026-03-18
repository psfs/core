<?php

namespace PSFS\controller;

use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\DeployHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;

/**
 * Class ConfigController
 * @package PSFS\controller
 */
class ConfigController extends Admin
{
    private static function assertSuperAdminConfigWriteAccess(): void
    {
        $security = Security::getInstance();
        $hasAdmins = count($security->getAdmins()) > 0;
        if ($hasAdmins && !$security->isSuperAdmin() && !Security::isTest()) {
            throw new ConfigException(t('Restricted area'));
        }
    }

    /**
     * Service that returns available configuration parameters
     * @GET
     * @route /admin/config/params
     * @label PSFS configuration parameters
     * @visible false
     * @return \PSFS\base\dto\JsonResponse(data=array)
     */
    public function getConfigParams()
    {
        $response = array_merge(Config::$required, Config::$optional);
        $domains = Router::getInstance()->getDomains();
        foreach ($domains as $domain => $routes) {
            $pDomain = str_replace('@', '', $domain);
            $pDomain = str_replace('/', '', $pDomain);
            $response[] = strtolower($pDomain) . '.api.secret';
        }
        return $this->json($response);
    }

    /**
     * Handles platform configuration
     * @GET
     * @Route /admin/config
     * @label General configuration
     * @icon fa-cogs
     * @return string HTML
     * @throws FormException
     */
    public function config()
    {
        Logger::log("Config loaded executed by " . $this->getRequest()->getRequestUri());
        if (defined('PSFS_UNIT_TESTING_EXECUTION')) {
            throw new ConfigException('CONFIG');
        }
        $form = new ConfigForm(Router::getInstance()->getRoute('admin-config'), Config::$required, Config::$optional, Config::getInstance()->dumpConfig());
        $form->build();
        return $this->render('welcome.html.twig', array(
            'text' => t("Welcome to PSFS"),
            'config' => $form,
            'typeahead_data' => array_merge(Config::$required, Config::$optional),
        ));
    }

    /**
     * Service that stores platform configuration
     * @POST
     * @route /admin/config
     * @visible false
     * @return string
     * @throws FormException|ConfigException
     */
    public function saveConfig()
    {
        self::assertSuperAdminConfigWriteAccess();
        Logger::log(t("Saving configuration"), LOG_INFO);
        $form = new ConfigForm(Router::getInstance()->getRoute('admin-config'), Config::$required, Config::$optional, Config::getInstance()->dumpConfig());
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $debug = Config::getInstance()->getDebugMode();
            if (Config::save($form->getData(), $form->getExtraData())) {
                Logger::log(t('Configuration saved successfully'));
                $runtimeDebug = (bool)Config::getParam('debug', false);
                // In production (debug=0) always refresh cache.var and invalidate config artifacts.
                if (!$runtimeDebug) {
                    DeployHelper::refreshCacheState();
                }
                // Check whether the DocumentRoot cache must be cleared.
                if (boolval($debug) !== $runtimeDebug) {
                    GeneratorHelper::clearDocumentRoot();
                }
                Security::getInstance()->setFlash("callback_message", t("Configuration updated successfully"));
                Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-config", true));
            } else {
                throw new ConfigException(t('Error while saving configuration, please verify filesystem permissions'));
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => t("Welcome to PSFS"),
            'config' => $form,
            'typeahead_data' => array_merge(Config::$required, Config::$optional),
        ));
    }
}
