<?php
namespace PSFS\controller;

use Exception;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;

/**
 * Class GeneratorController
 * @package PSFS\controller
 * @domain ROOT
 */
class GeneratorController extends Admin
{
    /**
     * @Injectable
     * @var \PSFS\services\GeneratorService Servicio de generación de estructura de directorios
     */
    protected $gen;

    /**
     * @label Método que genera un nuevo módulo
     * @GET
     * @route /admin/module
     * @icon fa-layer-plus
     * @return string
     * @throws FormException
     */
    public function generateModule()
    {
        Logger::log("Arranque generador de módulos al solicitar " . $this->getRequest()->getRequestUri());
        /* @var $form ConfigForm */
        $form = new ModuleForm();
        $form->build();
        return $this->render("modules.html.twig", array(
            'form' => $form,
        ));
    }

    /**
     * @POST
     * @route /admin/module
     * @label Generador de módulos
     * @return string
     */
    public function doGenerateModule()
    {
        $form = new ModuleForm();
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $module = strtoupper($form->getFieldValue("module"));
            $type = preg_replace('/normal/i', '', $form->getFieldValue("controllerType"));
            $apiClass = $form->getFieldValue("api");
            try {
                $module = preg_replace('/(\\\|\/)/', '/', $module);
                $module = preg_replace('/^\//', '', $module);
                GeneratorHelper::checkCustomNamespaceApi($apiClass);
                $this->gen->createStructureModule($module, false, $type, $apiClass);
                Security::getInstance()->setFlash("callback_message", str_replace("%s", $module, t("Módulo %s generado correctamente")));
                 Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-module", true));
            } catch (Exception $e) {
                Logger::log($e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
                Security::getInstance()->setFlash("callback_message", htmlentities($e->getMessage()));
            }
        }
        return $this->render("modules.html.twig", array(
            'form' => $form,
        ));
    }

}
