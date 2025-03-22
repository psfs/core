<?php

namespace PSFS\base\types\traits\Generator;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\traits\TemplateTrait;

/**
 * Trait StructureTrait
 * @package PSFS\base\types\traits\Generator
 */
trait StructureTrait
{
    use TemplateTrait;

    /**
     * Service that creates the root paths for the modules
     * @param string $module
     * @param string $modPath
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function createModulePath($module, $modPath)
    {
        // Creates the src folder
        GeneratorHelper::createDir($modPath);
        // Create module path
        GeneratorHelper::createDir($modPath . $module);
    }

    /**
     * Servicio que genera la estructura base
     * @param string $module
     * @param boolean $modPath
     * @return boolean
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function createModulePathTree($module, $modPath)
    {
        //Creamos las carpetas CORE del módulo
        Logger::log("Generamos la estructura");
        $paths = [
            "Api", "Config", "Controller", "Models", "Public", "Templates", "Services", "Test", "Doc",
            "Locale", "Locale/" . Config::getParam('default.locale', 'es_ES'), "Locale/" . Config::getParam('default.locale', 'es_ES') . "/LC_MESSAGES"
        ];
        $modulePath = $modPath . $module;
        foreach ($paths as $path) {
            GeneratorHelper::createDir($modulePath . DIRECTORY_SEPARATOR . $path);
        }
        //Creamos las carpetas de los assets
        $htmlPaths = array("css", "js", "img", "media", "font");
        foreach ($htmlPaths as $path) {
            GeneratorHelper::createDir($modulePath . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . $path);
        }
    }

    /**
     * @param string $modPath
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generatePublicTemplates($modPath, $force = false)
    {
        //Generamos el fichero de configuración
        Logger::log("Generamos ficheros para assets base");
        $css = $this->writeTemplateToFile("/* CSS3 STYLES */\n\n",
            $modPath . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . "styles.css",
            $force);
        $js = $this->writeTemplateToFile("/* APP MODULE JS */\n\n(function() {\n\t'use strict';\n})();",
            $modPath . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR . "app.js",
            $force);
        return ($css && $js);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generateServiceTemplate($module, $modPath, $force = false)
    {
        //Generamos el controlador base
        Logger::log("Generamos el servicio BASE");
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/service.template.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "class" => $class,
        ));
        return $this->writeTemplateToFile($controller,
            $modPath . DIRECTORY_SEPARATOR . "Services" . DIRECTORY_SEPARATOR . "{$class}Service.php",
            $force);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function genereateAutoloaderTemplate($module, $mod_path, $force = false)
    {
        //Generamos el autoloader del módulo
        Logger::log("Generamos el autoloader");
        $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
            "module" => $module,
            "autoloader" => preg_replace('/(\\\|\/)/', '_', $module),
            "regex" => preg_replace('/(\\\|\/)/m', '\\\\\\\\\\\\', $module),
        ));
        $autoload = $this->writeTemplateToFile($autoloader, $mod_path . DIRECTORY_SEPARATOR . "autoload.php", true);

        Logger::log("Generamos el phpunit");
        $phpUnitTemplate = $this->tpl->dump("generator/phpunit.template.twig", array(
            "module" => $module,
        ));
        $phpunit = $this->writeTemplateToFile($phpUnitTemplate, $mod_path . DIRECTORY_SEPARATOR . "phpunit.xml.dist", $force);
        return $autoload && $phpunit;
    }
}
