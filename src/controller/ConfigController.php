<?php

namespace PSFS\controller;

use HttpException;
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
     * Servicio que guarda la configuración de la plataforma
     * @POST
     * @route /admin/config
     * @visible false
     * @return string
     * @throws FormException|HttpException
     */
    public function saveConfig()
    {
        Logger::log(t("Saving configuration"), LOG_INFO);
        $form = new ConfigForm(Router::getInstance()->getRoute('admin-config'), Config::$required, Config::$optional, Config::getInstance()->dumpConfig());
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $debug = Config::getInstance()->getDebugMode();
            if (Config::save($form->getData(), $form->getExtraData())) {
                Logger::log(t('Configuration saved successfully'));
                $runtimeDebug = (bool)Config::getParam('debug', false);
                // En producción (debug=0) siempre refrescamos cache.var e invalidamos artefactos de config.
                if (!$runtimeDebug) {
                    DeployHelper::refreshCacheState();
                }
                // Verificamos si tenemos que limpiar la cache del DocumentRoot.
                if (boolval($debug) !== $runtimeDebug) {
                    GeneratorHelper::clearDocumentRoot();
                }
                Security::getInstance()->setFlash("callback_message", t("Configuration updated successfully"));
                Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-config", true));
            } else {
                throw new HttpException(t('Error while saving configuration, please verify filesystem permissions'), 403);
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => t("Welcome to PSFS"),
            'config' => $form,
            'typeahead_data' => array_merge(Config::$required, Config::$optional),
        ));
    }
}
