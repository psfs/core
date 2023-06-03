<?php
namespace PSFS\controller;

use HttpException;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;

/**
 * Class ConfigController
 * @package PSFS\controller
 */
class ConfigController extends Admin
{

    /**
     * Servicio que devuelve los parámetros disponibles
     * @GET
     * @route /admin/config/params
     * @label Parámetros de configuración de PSFS
     * @visible false
     * @return JsonResponse(data=array)
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
     * Método que gestiona la configuración de las variables
     * @GET
     * @Route /admin/config
     * @label Configuración general
     * @icon fa-cogs
     * @return string HTML
     * @throws FormException
     */
    public function config()
    {
        Logger::log("Config loaded executed by " . $this->getRequest()->getRequestUri());
        if(defined('PSFS_UNIT_TESTING_EXECUTION')) {
            throw new ConfigException('CONFIG');
        }
        $form = new ConfigForm(Router::getInstance()->getRoute('admin-config'), Config::$required, Config::$optional, Config::getInstance()->dumpConfig());
        $form->build();
        return $this->render('welcome.html.twig', array(
            'text' => t("Bienvenido a PSFS"),
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
        Logger::log(t("Guardando configuración"), LOG_INFO);
        $form = new ConfigForm(Router::getInstance()->getRoute('admin-config'), Config::$required, Config::$optional, Config::getInstance()->dumpConfig());
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $debug = Config::getInstance()->getDebugMode();
            $newDebug = $form->getFieldValue("debug");
            if (Config::save($form->getData(), $form->getExtraData())) {
                Logger::log(t('Configuración guardada correctamente'));
                //Verificamos si tenemos que limpiar la cache del DocumentRoot
                if (boolval($debug) !== boolval($newDebug)) {
                    GeneratorHelper::clearDocumentRoot();
                }
                Security::getInstance()->setFlash("callback_message", t("Configuración actualizada correctamente"));
                Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-config", true));
            } else {
                throw new HttpException(t('Error al guardar la configuración, prueba a cambiar los permisos'), 403);
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => t("Bienvenido a PSFS"),
            'config' => $form,
            'typeahead_data' => array_merge(Config::$required, Config::$optional),
        ));
    }
}
