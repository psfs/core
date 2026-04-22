<?php

namespace PSFS\controller;

use Exception;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\exception\FormException;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;
use PSFS\services\GeneratorService;

/**
 * Class GeneratorController
 * @package PSFS\controller
 */
class GeneratorController extends Admin
{
    #[Injectable(class: GeneratorService::class)]
    protected GeneratorService $gen;

    /**
     * @throws FormException
     */
    #[Label('Generate new module')]
    #[HttpMethod('GET')]
    #[Route('/admin/module')]
    #[Icon('fa-layer-plus')]
    public function generateModule()
    {
        Logger::log("Module generator started for request " . $this->getRequest()->getRequestUri());
        /* @var $form ConfigForm */
        $form = new ModuleForm();
        $form->build();
        return $this->render("modules.html.twig", array(
            'form' => $form,
        ));
    }

    #[HttpMethod('POST')]
    #[Route('/admin/module')]
    #[Label('Module generator')]
    public function doGenerateModule()
    {
        $form = new ModuleForm();
        $form->build();
        $form->hydrate();
        if ($form->isValid()) {
            $module = strtoupper((string)$form->getFieldValue("module"));
            $type = preg_replace('/normal/i', '', (string)$form->getFieldValue("controllerType"));
            $apiClass = (string)$form->getFieldValue("api");
            try {
                $module = preg_replace('/(\\\|\/)/', '/', $module);
                $module = preg_replace('/^\//', '', $module);
                GeneratorHelper::checkCustomNamespaceApi($apiClass);
                $this->gen->createStructureModule($module, false, $type, $apiClass);
                Security::getInstance()->setFlash(
                    "callback_message",
                    str_replace("%s", $module, t("Module %s generated successfully"))
                );
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
