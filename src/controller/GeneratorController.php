<?php
namespace PSFS\controller;

use PSFS\base\config\ModuleForm;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\controller\base\Admin;
use PSFS\Services\GeneratorService;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GeneratorController
 * @package PSFS\controller
 * @domain ROOT
 */
class GeneratorController extends Admin
{
    /**
     * @Injectable
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
                // Security::getInstance()->setFlash("callback_route", $this->getRoute("admin-module", true));
            } catch (\Exception $e) {
                Logger::log($e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
                Security::getInstance()->setFlash("callback_message", htmlentities($e->getMessage()));
            }
        }
        return $this->render("modules.html.twig", array(
            'form' => $form,
        ));
    }

    public static function createRoot($path = WEB_DIR, OutputInterface $output = null) {

        if(null === $output) {
            $output = new ConsoleOutput();
        }

        GeneratorHelper::createDir($path);
        $paths = array("js", "css", "img", "media", "font");
        foreach ($paths as $htmlPath) {
            GeneratorHelper::createDir($path . DIRECTORY_SEPARATOR . $htmlPath);
        }

        // Generates the root needed files
        $files = [
            'index' => 'index.php',
            'browserconfig' => 'browserconfig.xml',
            'crossdomain' => 'crossdomain.xml',
            'humans' => 'humans.txt',
            'robots' => 'robots.txt',
        ];
        foreach ($files as $templates => $filename) {
            $text = Template::getInstance()->dump("generator/html/" . $templates . '.html.twig');
            if (false === file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $text)) {
                $output->writeln('Can\t create the file ' . $filename);
            } else {
                $output->writeln($filename . ' created successfully');
            }
        }

        //Export base locale translations
        if (!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale')) {
            GeneratorHelper::createDir(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            GeneratorService::copyr(SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale', BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        }
    }
}