<?php
namespace PSFS\Services;

use Propel\Common\Config\ConfigurationManager;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\AbstractManager;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Manager\ModelManager;
use Propel\Generator\Manager\SqlManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Schema;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Service;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Filesystem\Filesystem;

class GeneratorService extends Service
{
    /**
     * @Injectable
     * @var \PSFS\base\config\Config Servicio de configuración
     */
    protected $config;
    /**
     * @Injectable
     * @var \PSFS\base\Security Servicio de autenticación
     */
    protected $security;
    /**
     * @Injectable
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
                GeneratorHelper::createDir($locale_path);
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
     * @param string $apiClass
     * @return mixed
     */
    public function createStructureModule($module, $force = false, $type = "", $apiClass = "")
    {
        $mod_path = CORE_DIR . DIRECTORY_SEPARATOR;
        $module = ucfirst($module);
        $this->createModulePath($module, $mod_path);
        $this->createModulePathTree($module, $mod_path);
        $this->createModuleBaseFiles($module, $mod_path, $force, $type);
        $this->createModuleModels($module, $mod_path);
        $this->generateBaseApiTemplate($module, $mod_path, $force, $apiClass);
        //Redireccionamos al home definido
        $this->log->addLog("Módulo generado correctamente");
    }

    /**
     * Service that creates the root paths for the modules
     * @param string $module
     * @param string $mod_path
     */
    private function createModulePath($module, $mod_path)
    {
        // Creates the src folder
        GeneratorHelper::createDir($mod_path);
        // Create module path
        GeneratorHelper::createDir($mod_path . $module);
    }

    /**
     * Servicio que genera la estructura base
     * @param string $module
     * @param boolean $mod_path
     * @return boolean
     */
    private function createModulePathTree($module, $mod_path)
    {
        //Creamos las carpetas CORE del módulo
        $this->log->addLog("Generamos la estructura");
        $paths = [
            "Api", "Api/base", "Config", "Controller", "Models", "Public", "Templates", "Services", "Test", "Doc",
            "Locale", "Locale/" . Config::getParam('default.locale', 'es_ES'), "Locale/" . Config::getParam('default.locale', 'es_ES') . "/LC_MESSAGES"
        ];
        $module_path = $mod_path . $module;
        foreach ($paths as $path) {
            GeneratorHelper::createDir($module_path . DIRECTORY_SEPARATOR . $path);
        }
        //Creamos las carpetas de los assets
        $htmlPaths = array("css", "js", "img", "media", "font");
        foreach ($htmlPaths as $path) {
            GeneratorHelper::createDir($module_path . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . $path);
        }
    }

    /**
     * Servicio que genera las plantillas básicas de ficheros del módulo
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param string $controllerType
     */
    private function createModuleBaseFiles($module, $mod_path, $force = false, $controllerType = '')
    {
        $module_path = $mod_path . $module;
        $this->generateControllerTemplate($module, $module_path, $force, $controllerType);
        $this->generateServiceTemplate($module, $module_path, $force);
        $this->genereateAutoloaderTemplate($module, $module_path, $force);
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
     */
    private function createModuleModels($module, $path)
    {
        $module_path = $path . $module;
        $module_path = str_replace(CORE_DIR . DIRECTORY_SEPARATOR, '', $module_path);

        $configGenerator = $this->getConfigGenerator($module_path);

        $this->buildModels($configGenerator);
        $this->buildSql($configGenerator);

        $configTemplate = $this->tpl->dump("generator/config.propel.template.twig", array(
            "module" => $module,
        ));
        $this->writeTemplateToFile($configTemplate, CORE_DIR . DIRECTORY_SEPARATOR . $module_path . DIRECTORY_SEPARATOR . "Config" .
            DIRECTORY_SEPARATOR . "config.php", true);
        $this->log->addLog("Generado config genérico para propel");
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
        $this->log->addLog("Generamos el controlador BASE");
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

        $filename = $mod_path . DIRECTORY_SEPARATOR . "Test" . DIRECTORY_SEPARATOR . "{$class}Test.php";
        $test = true;
        if(!file_exists($filename)) {
            $testTemplate = $this->tpl->dump("generator/testCase.template.twig", array(
                "module" => $module,
                "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
                "class" => $class,
            ));
            $test = $this->writeTemplateToFile($testTemplate, $filename, true);
        }
        return ($controller && $controllerBase && $test);
    }

    /**
     * @param string $module
     * @param string $mod_path
     * @param boolean $force
     * @param string $apiClass
     * @return boolean
     */
    private function generateBaseApiTemplate($module, $mod_path, $force = false, $apiClass = "")
    {
        $created = true;
        $modelPath = $mod_path . $module . DIRECTORY_SEPARATOR . 'Models';
        $api_path = $mod_path . $module . DIRECTORY_SEPARATOR . 'Api';
        if (file_exists($modelPath)) {
            $dir = dir($modelPath);
            while ($file = $dir->read()) {
                if (!in_array($file, array('.', '..'))
                    && !preg_match('/Query\.php$/i', $file)
                    && preg_match('/\.php$/i', $file)
                ) {
                    $filename = str_replace(".php", "", $file);
                    $this->log->addLog("Generamos Api BASES para {$filename}");
                    $this->createApiBaseFile($module, $api_path, $filename, $apiClass);
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
        $this->log->addLog("Generamos fichero vacío de configuración");
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
        $this->log->addLog("Generamos ficheros para assets base");
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
        $this->log->addLog("Generamos el servicio BASE");
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
     * @return boolean
     */
    private function genereateAutoloaderTemplate($module, $mod_path, $force = false)
    {
        //Generamos el autoloader del módulo
        $this->log->addLog("Generamos el autoloader");
        $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
            "module" => $module,
            "autoloader" => preg_replace('/(\\\|\/)/', '_', $module),
            "regex" => preg_replace('/(\\\|\/)/m', '\\\\\\\\\\\\', $module),
        ));
        $autoload = $this->writeTemplateToFile($autoloader, $mod_path . DIRECTORY_SEPARATOR . "autoload.php", $force);

        $this->log->addLog("Generamos el phpunit");
        $phpUnitTemplate = $this->tpl->dump("generator/phpunit.template.twig", array(
            "module" => $module,
        ));
        $phpunit = $this->writeTemplateToFile($phpUnitTemplate, $mod_path . DIRECTORY_SEPARATOR . "phpunit.xml.dist", $force);
        return $autoload && $phpunit;
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
        $this->log->addLog("Generamos el schema");
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
        $this->log->addLog("Generamos la configuración de Propel");
        $build_properties = $this->tpl->dump("generator/build.properties.twig", array(
            "module" => $module,
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
        $this->log->addLog("Generamos una plantilla base por defecto");
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
     * @param string $apiClass
     *
     * @return bool
     */
    private function createApiBaseFile($module, $mod_path, $api, $apiClass = '')
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $customClass = GeneratorHelper::extractClassFromNamespace($apiClass);
        $controller = $this->tpl->dump("generator/api.base.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            'customClass' => $customClass,
            'customNamespace' => $apiClass,
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
        GeneratorHelper::createDir($dst);
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

    /**
     * @param $module_path
     * @return array
     */
    private function getPropelPaths($module_path)
    {
        $moduleDir = CORE_DIR . DIRECTORY_SEPARATOR . $module_path;
        GeneratorHelper::createDir($moduleDir);
        $moduleDir = realpath($moduleDir);
        $configDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config';
        $sqlDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql';
        $migrationDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations';
        $paths = [
            'projectDir' => $moduleDir,
            'outputDir' => $moduleDir,
            'phpDir' => $moduleDir,
            'phpConfDir' => $configDir,
            'sqlDir' => $sqlDir,
            'migrationDir' => $migrationDir,
            'schemaDir' => $configDir,
        ];
        return $paths;
    }

    /**
     * @param string $module_path
     * @return GeneratorConfig
     */
    private function getConfigGenerator($module_path)
    {
        // Generate the configurator
        $paths = $this->getPropelPaths($module_path);
        foreach ($paths as $path) {
            GeneratorHelper::createDir($path);
        }
        $configGenerator = new GeneratorConfig($paths['phpConfDir'], [
            'propel' => [
                'paths' => $paths,
            ]
        ]);
        return $configGenerator;
    }

    /**
     * @param GeneratorConfig $configGenerator
     */
    private function buildModels(GeneratorConfig $configGenerator)
    {
        $manager = new ModelManager();
        $manager->setFilesystem(new Filesystem());
        $this->setupManager($configGenerator, $manager);
        $manager->build();
    }

    /**
     * @param GeneratorConfig $configGenerator
     */
    private function buildSql(GeneratorConfig $configGenerator)
    {
        $manager = new SqlManager();
        $connections = $configGenerator->getBuildConnections();
        $manager->setConnections($connections);
        $manager->setValidate(true);
        $this->setupManager($configGenerator, $manager, $configGenerator->getSection('paths')['sqlDir']);

        $manager->buildSql();
    }

    /**
     * @param GeneratorConfig $configGenerator
     * @param AbstractManager $manager
     * @param string $workingDir
     */
    private function setupManager(GeneratorConfig $configGenerator, AbstractManager &$manager, $workingDir = CORE_DIR)
    {
        $manager->setGeneratorConfig($configGenerator);
        $schemaFile = new \SplFileInfo($configGenerator->getSection('paths')['schemaDir'] . DIRECTORY_SEPARATOR . 'schema.xml');
        $manager->setSchemas([$schemaFile]);
        $manager->setLoggerClosure(function ($message) {
            Logger::log($message, LOG_INFO);
        });
        $manager->setWorkingDirectory($workingDir);
    }
}
