<?php
namespace PSFS\controller;

use PSFS\base\config\ModuleForm;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\controller\base\Admin;

/**
 * Class GeneratorController
 * @package PSFS\controller
 * @domain ROOT
 */
class GeneratorController extends Admin
{
    /**
     * @Inyectable
     * @var  \PSFS\services\GeneratorService Servicio de generación de estructura de directorios
     */
    protected $gen;

    /**
     * Método que genera un nuevo módulo
     * @GET
     * @route /admin/module
     *
     * @return string HTML
     * @throws \HttpException
     */
    public function generateModule()
    {
        Logger::log("Arranque generador de módulos al solicitar " . $this->getRequest()->getRequestUri());
        /* @var $form \PSFS\base\config\ConfigForm */
        $form = new ModuleForm();
        $form->build();
        return $this->render("modules.html.twig", array(
            'properties' => $this->config->getPropelParams(),
            'form' => $form,
        ));
    }

    /**
     * @POST
     * @route /admin/module
     * @return string
     */
    public function doGenerateModule()
    {
        $form = new ModuleForm();
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $module = $form->getFieldValue("module");
            $type = $form->getFieldValue("controllerType");
            $is_module = false;
            try {
                $module = preg_replace('/(\\\|\/)/', '/', $module);
                $module = preg_replace('/^\//', '', $module);
                $this->gen->createStructureModule($module, false, $type, (bool)$is_module);
                Security::getInstance()->setFlash("callback_message", str_replace("%s", $module, _("Módulo %s generado correctamente")));
                Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-module", true));
            } catch (\Exception $e) {
                Logger::getInstance()->infoLog($e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
                throw new ConfigException('Error al generar el módulo, prueba a cambiar los permisos', 403);
            }
        }
        return $this->render("modules.html.twig", array(
            'properties' => $this->config->getPropelParams(),
            'form' => $form,
        ));
    }
}