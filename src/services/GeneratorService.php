<?php
namespace PSFS\Services;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Service;

class GeneratorService extends Service
{
    /**
     * @Inyectable
     * @var \PSFS\base\config\Config Servicio de configuración
     */
    protected $config;
    /**
     * @Inyectable
     * @var \PSFS\base\Security Servicio de autenticación
     */
    protected $security;
    /**
     * @Inyectable
     * @var \PSFS\base\Template Servicio de gestión de plantillas
     */
    protected $tpl;

    /**
     * Método que revisa las traducciones directorio a directorio
     * @param $path
     * @param $locale
     * @return array
     */
    public static function findTranslations($path, $locale)
    {
        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = array();
        if (file_exists($path)) {
            $d = dir($path);
            while (false !== ($dir = $d->read())) {
                Config::createDir($locale_path);
                if (!file_exists($locale_path . 'translations.po')) {
                    file_put_contents($locale_path . 'translations.po', '');
                }
                $inspect_path = realpath($path . DIRECTORY_SEPARATOR . $dir);
                $cmd_php = "export PATH=\$PATH:/opt/local/bin; xgettext " .
                    $inspect_path . DIRECTORY_SEPARATOR .
                    "*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$locale_path}translations.po";
                if (is_dir($path . DIRECTORY_SEPARATOR . $dir) && preg_match('/^\./', $dir) == 0) {
                    $res = _('Revisando directorio: ') . $inspect_path;
                    $res .= _('Comando ejecutado: ') . $cmd_php;
                    $res .= shell_exec($cmd_php);
                    usleep(10);
                    $translations[] = $res;
                    $translations = array_merge($translations, self::findTranslations($inspect_path, $locale));
                }
            }
        }
        return $translations;
    }

    /**
     * Servicio que genera la estructura de un módulo o lo actualiza en caso de ser necesario
     * @param string $module
     * @param boolean $force
     * @param string $type
     * @param boolean $isModule
     * @return mixed
     */
    public function createStructureModule($module, $force = false, $type = "", $isModule = false)
    {
        $mod_path = CORE_DIR . DIRECTORY_SEPARATOR;
        $module = ucfirst($module);
        $this->createModulePath($module, $mod_path, $isModule);
        $this->createModulePathTree($module, $mod_path, $isModule);
        $this->createModuleBaseFiles($module, $mod_path, $force, $type, $isModule);
        $this->createModuleModels($module, $mod_path, $isModule);
        $this->generateBaseApiTemplate($module, $mod_path, $force, $isModule);
        //Redireccionamos al home definido
        $this->log->infoLog("Módulo generado correctamente");
    }

    /**
     * Service that creates the root paths for the modules
     * @param string $module
     * @param string $mod_path
     * @param boolean $isModule
     */
    private function createModulePath($module, $mod_path, $isModule = false)
    {
        // Creates the src folder
        Config::createDir($mod_path);
        // Create module path
        if (false === $isModule) {
            Config::createDir($mod_path . $module);
        }
    }

    /**
     * Servicio que genera la estructura base
     * @param $module
     * @param $mod_path
     * @param boolean $isModule
     */
    private function createModulePathTree($module, $mod_path, $isModule = false)
    {
        //Creamos las carpetas CORE del módulo
        $this->log->infoLog("Generamos la estructura");
        $paths = [
            "Api", "Api/base", "Config", "Controller", "Form", "Models", "Public", "Templates", "Services", "Test"
        ];
        $module_path = $isModule ? $mod_path : $mod_path . $module;
        foreach ($paths as $path) {
            Config::createDir($module_path . DIRECTORY_SEPARATOR . $path);
        }
        //Creamos las carpetas de los assets
        $htmlPaths = array("css", "js", "img", "media", "font");
        foreach ($htmlPaths as $path) {
            Config::createDir($module_path . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . $path);
        }

        if ($isModule) {
            return $this->writeTemplateToFile(json_encode([
                "module" => "\\" . preg_replace('/(\\\|\/)/', '\\\\', $module),
            ], JSON_PRETTY_PRINT), $mod_path . DIRECTORY_SEPARATOR . "module.json", true);
        }
    }

    /**
     * Servicio que genera las plantillas básicas de ficheros del módulo
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param string $controllerType
     * @param boolean $isModule
     */
    private function createModuleBaseFiles($module, $mod_path, $force = false, $controllerType = '', $isModule = false)
    {
        $module_path = $isModule ? $mod_path : $mod_path . $module;
        $this->generateControllerTemplate($module, $module_path, $force, $controllerType);
        $this->generateServiceTemplate($module, $module_path, $force);
        $this->genereateAutoloaderTemplate($module, $module_path, $force, $isModule);
        $this->generateSchemaTemplate($module, $module_path, $force);
        $this->generatePropertiesTemplate($module, $module_path, $force);
        $this->generateConfigTemplate($module_path, $force);
        $this->generateIndexTemplate($module, $module_path, $force);
        $this->generatePublicTemplates($module_path, $force);
    }

    /**
     * Servicio que ejecuta Propel y genera el modelo de datos
     * @param string $module
     * @param string $path
     * @param boolean $isModule
     */
    private function createModuleModels($module, $path, $isModule = false)
    {
        $module_path = $isModule ? $path : $path . $module;
        $module_path = str_replace(CORE_DIR . DIRECTORY_SEPARATOR, '', $module_path);
        //Generamos las clases de propel y la configuración
        $exec = "export PATH=\$PATH:/opt/local/bin; " . BASE_DIR . DIRECTORY_SEPARATOR .
            "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "propel ";
        $schemaOpt = " --schema-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module_path .
            DIRECTORY_SEPARATOR . "Config";
        $opt = " --config-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module_path . DIRECTORY_SEPARATOR .
            "Config --output-dir=" . CORE_DIR . " --verbose";
        $this->log->infoLog("[GENERATOR] Ejecutamos propel:\n" . $exec . "build" . $opt . $schemaOpt);
        $ret = shell_exec($exec . "build" . $opt . $schemaOpt);

        $this->log->infoLog("[GENERATOR] Generamos clases invocando a propel:\n $ret");
        $ret = shell_exec($exec . "sql:build" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR .
            $module_path . DIRECTORY_SEPARATOR . "Config" . $schemaOpt);
        $this->log->infoLog("[GENERATOR] Generamos sql invocando a propel:\n $ret");
        $ret = shell_exec($exec . "config:convert" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR .
            $module_path . DIRECTORY_SEPARATOR . "Config");
        $this->log->infoLog("[GENERATOR] Generamos configuración invocando a propel:\n $ret");
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param string $controllerType
     * @return boolean
     */
    private function generateControllerTemplate($module, $mod_path, $force = false, $controllerType = "")
    {
        //Generamos el controlador base
        $this->log->infoLog("Generamos el controlador BASE");
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controllerBody = $this->tpl->dump("generator/controller.template.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            "controllerType" => $class . "Base",
            "is_base" => false
        ));
        $controller = $this->writeTemplateToFile($controllerBody, $mod_path . DIRECTORY_SEPARATOR . "Controller" .
            DIRECTORY_SEPARATOR . "{$class}Controller.php", $force);

        $controllerBody = $this->tpl->dump("generator/controller.template.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class . "Base",
            "service" => $class,
            "controllerType" => $controllerType,
            "is_base" => true,
            "domain" => $class,
        ));
        $controllerBase = $this->writeTemplateToFile($controllerBody, $mod_path . DIRECTORY_SEPARATOR . "Controller" .
            DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "{$class}BaseController.php", true);
        return ($controller && $controllerBase);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param boolean $isModule
     * @return boolean
     */
    private function generateBaseApiTemplate($module, $mod_path, $force = false, $isModule = false)
    {
        $created = true;
        $modelPath = $isModule ?
            $mod_path . DIRECTORY_SEPARATOR . 'Models' :
            $mod_path . $module . DIRECTORY_SEPARATOR . 'Models';
        $api_path = $isModule ?
            $mod_path . DIRECTORY_SEPARATOR . 'Api' :
            $mod_path . $module . DIRECTORY_SEPARATOR . 'Api';
        if (file_exists($modelPath)) {
            $dir = dir($modelPath);
            while ($file = $dir->read()) {
                if (!in_array($file, array('.', '..'))
                    && !preg_match('/Query\.php$/i', $file)
                    && preg_match('/\.php$/i', $file)
                ) {
                    $filename = str_replace(".php", "", $file);
                    $this->log->infoLog("Generamos Api BASES para {$filename}");
                    $this->createApiBaseFile($module, $api_path, $filename);
                    $this->createApi($module, $api_path, $force, $filename);
                }
            }
        }
        return $created;
    }

    /**
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generateConfigTemplate($mod_path, $force = false)
    {
        //Generamos el fichero de configuración
        $this->log->infoLog("Generamos fichero vacío de configuración");
        return $this->writeTemplateToFile("<?php\n\t",
            $mod_path . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "config.php",
            $force);
    }

    /**
     * @param string $mod_path
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generatePublicTemplates($mod_path, $force = false)
    {
        //Generamos el fichero de configuración
        $this->log->infoLog("Generamos ficheros para assets base");
        $css = $this->writeTemplateToFile("/* CSS3 STYLES */\n\n",
            $mod_path . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . "styles.css",
            $force);
        $js = $this->writeTemplateToFile("/* APP MODULE JS */\n\n(function() {\n\t'use strict';\n})();",
            $mod_path . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR . "app.js",
            $force);
        return ($css && $js);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generateServiceTemplate($module, $mod_path, $force = false)
    {
        //Generamos el controlador base
        $this->log->infoLog("Generamos el servicio BASE");
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/service.template.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "class" => $class,
        ));
        return $this->writeTemplateToFile($controller,
            $mod_path . DIRECTORY_SEPARATOR . "Services" . DIRECTORY_SEPARATOR . "{$class}Service.php",
            $force);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param boolean $isModule
     * @return boolean
     */
    private function genereateAutoloaderTemplate($module, $mod_path, $force = false, $isModule = false)
    {
        //Generamos el autoloader del módulo
        $this->log->infoLog("Generamos el autoloader");
        $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
            "module" => $module,
            "autoloader" => preg_replace('/(\\\|\/)/', '_', $module),
            "regex" => preg_replace('/(\\\|\/)/m', '\\\\\\\\\\\\', $module),
            "is_module" => $isModule,
        ));
        return $this->writeTemplateToFile($autoloader, $mod_path . DIRECTORY_SEPARATOR . "autoload.php", $force);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generateSchemaTemplate($module, $mod_path, $force = false)
    {
        //Generamos el autoloader del módulo
        $this->log->infoLog("Generamos el schema");
        $schema = $this->tpl->dump("generator/schema.propel.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '', $module),
            "prefix" => preg_replace('/(\\\|\/)/', '', $module),
            "db" => $this->config->get("db_name"),
        ));
        return $this->writeTemplateToFile($schema,
            $mod_path . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml",
            $force);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generatePropertiesTemplate($module, $mod_path, $force = false)
    {
        $this->log->infoLog("Generamos la configuración de Propel");
        $build_properties = $this->tpl->dump("generator/build.properties.twig", array(
            "module" => $module,
            "host" => $this->config->get("db_host"),
            "port" => $this->config->get("db_port"),
            "user" => $this->config->get("db_user"),
            "pass" => $this->config->get("db_password"),
            "db" => $this->config->get("db_name"),
            "namespace" => preg_replace('/(\\\|\/)/', '', $module),
        ));
        return $this->writeTemplateToFile($build_properties,
            $mod_path . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml",
            $force);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @return boolean
     */
    private function generateIndexTemplate($module, $mod_path, $force = false)
    {
        //Generamos la plantilla de index
        $this->log->infoLog("Generamos una plantilla base por defecto");
        $index = $this->tpl->dump("generator/index.template.twig", array(
            "module" => $module,
        ));
        return $this->writeTemplateToFile($index,
            $mod_path . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig",
            $force);
    }

    /**
     * Método que graba el contenido de una plantilla en un fichero
     * @param string $fileContent
     * @param string $filename
     * @param boolean $force
     * @return boolean
     */
    private function writeTemplateToFile($fileContent, $filename, $force = false)
    {
        $created = false;
        if ($force || !file_exists($filename)) {
            try {
                $this->cache->storeData($filename, $fileContent, Cache::TEXT, true);
                $created = true;
            } catch (\Exception $e) {
                $this->log->errorLog($e->getMessage());
            }
        } else {
            $this->log->errorLog($filename . _(' not exists or cant write'));
        }
        return $created;
    }

    /**
     * Create ApiBase
     * @param string $module
     * @param string $mod_path
     * @param string $api
     *
     * @return bool
     */
    private function createApiBaseFile($module, $mod_path, $api)
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/api.base.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
        ));

        return $this->writeTemplateToFile($controller,
            $mod_path . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . "{$api}BaseApi.php", true);
    }

    /**
     * Create Api
     * @param string $module
     * @param string $mod_path
     * @param bool $force
     * @param string $api
     *
     * @return bool
     */
    private function createApi($module, $mod_path, $force, $api)
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/api.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
        ));

        return $this->writeTemplateToFile($controller, $mod_path . DIRECTORY_SEPARATOR . "{$api}.php", $force);
    }

    /**
     * Method that copy resources recursively
     * @param string $dest
     * @param boolean $force
     * @param $filename_path
     * @param boolean $debug
     */
    public static function copyResources($dest, $force, $filename_path, $debug)
    {
        if (file_exists($filename_path)) {
            $destfolder = basename($filename_path);
            if (!file_exists(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug || $force) {
                if (is_dir($filename_path)) {
                    self::copyr($filename_path, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                } else {
                    if (@copy($filename_path, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) === FALSE) {
                        throw new ConfigException("Can't copy " . $filename_path . " to " . WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                    }
                }
            }
        }
    }

    /**
     * Method that copy a resource
     * @param string $src
     * @param string $dst
     * @throws ConfigException
     */
    public static function copyr($src, $dst)
    {
        $dir = opendir($src);
        Config::createDir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copyr($src . '/' . $file, $dst . '/' . $file);
                } elseif (@copy($src . '/' . $file, $dst . '/' . $file) === false) {
                    throw new ConfigException("Can't copy " . $src . " to " . $dst);
                }
            }
        }
        closedir($dir);
    }
}
