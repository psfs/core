<?php

namespace PSFS\services;

use Propel\Common\Config\ConfigurationManager;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Diff\DatabaseComparator;
use Propel\Generator\Model\Schema;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Generator\PropelHelperTrait;
use PSFS\base\types\traits\Generator\StructureTrait;

/**
 * Class GeneratorService
 * @package PSFS\services
 */
class GeneratorService extends SimpleService
{
    use PropelHelperTrait;
    use StructureTrait;

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
     * Servicio que genera la estructura de un módulo o lo actualiza en caso de ser necesario
     * @param string $module
     * @param boolean $force
     * @param string $type
     * @param string $apiClass
     * @param bool $skipMigration
     * @throws GeneratorException
     * @throws \ReflectionException
     */
    public function createStructureModule(string $module, bool $force = false, string $type = "", string $apiClass = "", bool $skipMigration = false): void
    {
        $modPath = CORE_DIR . DIRECTORY_SEPARATOR;
        $module = ucfirst($module);
        $this->createModulePath($module, $modPath);
        $this->createModulePathTree($module, $modPath);
        $this->createModuleBaseFiles($module, $modPath, $force, $type);
        $this->createModuleModels($module, $modPath);
        $this->generateBaseApiTemplate($module, $modPath, $force, $apiClass);
        if (!$skipMigration) {
            $this->createModuleMigrations($module, $modPath);
        }
        //Redireccionamos al home definido
        Logger::log("Módulo generado correctamente");
    }

    /**
     * Servicio que genera las plantillas básicas de ficheros del módulo
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @param string $controllerType
     * @throws GeneratorException
     */
    private function createModuleBaseFiles($module, $modPath, $force = false, $controllerType = '')
    {
        $modulePath = $modPath . $module;
        $this->generateControllerTemplate($module, $modulePath, $force, $controllerType);
        $this->generateServiceTemplate($module, $modulePath, $force);
        $this->generateSchemaTemplate($module, $modulePath, $force);
        $this->generateConfigurationTemplates($module, $modulePath, $force);
        $this->generateIndexTemplate($module, $modulePath, $force);
        $this->generatePublicTemplates($modulePath, $force);
    }

    /**
     * @param string $module
     * @param string $modulePath
     * @param bool $force
     * @return void
     * @throws GeneratorException
     */
    public function generateConfigurationTemplates(string $module, string $modulePath, bool $force = false): void
    {
        $this->genereateAutoloaderTemplate($module, $modulePath, $force);
        $this->generatePropertiesTemplate($module, $modulePath, $force);
        $this->generateConfigTemplate($modulePath, $force);
        $this->createModuleModels($module, CORE_DIR . DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @param string $controllerType
     * @return boolean
     */
    private function generateControllerTemplate($module, $modPath, $force = false, $controllerType = "")
    {
        //Generamos el controlador base
        Logger::log("Generamos el controlador BASE");
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controllerBody = $this->tpl->dump("generator/controller.template.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            "controllerType" => $class . "Base",
            "is_base" => false
        ));
        $controller = $this->writeTemplateToFile($controllerBody, $modPath . DIRECTORY_SEPARATOR . "Controller" .
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
        $controllerBase = $this->writeTemplateToFile($controllerBody, $modPath . DIRECTORY_SEPARATOR . "Controller" .
            DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "{$class}BaseController.php", true);

        $filename = $modPath . DIRECTORY_SEPARATOR . "Test" . DIRECTORY_SEPARATOR . "{$class}Test.php";
        $test = true;
        if (!file_exists($filename)) {
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
     * Servicio que ejecuta Propel y genera el modelo de datos
     * @param string $module
     * @param string $path
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function createModuleModels($module, $path)
    {
        $modulePath = str_replace(CORE_DIR . DIRECTORY_SEPARATOR, '', $path . $module);
        $configGenerator = $this->getConfigGenerator($modulePath);

        $this->buildModels($configGenerator);
        $this->buildSql($configGenerator);

        $configTemplate = $this->tpl->dump("generator/config.propel.template.twig", array(
            "module" => $module,
        ));
        $this->writeTemplateToFile($configTemplate, CORE_DIR . DIRECTORY_SEPARATOR . $modulePath . DIRECTORY_SEPARATOR . "Config" .
            DIRECTORY_SEPARATOR . "config.php", true);
        Logger::log("Generado config genérico para propel");
    }

    /**
     * @throws GeneratorException
     */
    private function createModuleMigrations($module, $path)
    {
        $migrationService = MigrationService::getInstance();
        /** @var $manager MigrationManager */
        /** @var $generatorConfig GeneratorConfig */
        list($manager, $generatorConfig) = $migrationService->getConnectionManager($module, $path);

        if ($manager->hasPendingMigrations()) {
            throw new ApiException(t(sprintf('Módulo %s generado correctamente. Hay una migración pendiente de aplicar, ejecute comando `psfs:migrate` o elimine el fichero generado en el módulo', $module)), 400);
        }

        // Checking the schema xml as source of tables and modifications
        $totalNbTables = 0;
        $reversedSchema = new Schema();
        $debugLogger = Config::getParam('log.level') === 'DEBUG';
        $connections = $generatorConfig->getBuildConnections();
        /** @var Database $appDatabase */
        foreach ($manager->getDatabases() as $appDatabase) {
            list($database, $nbTables) = $migrationService->checkSourceDatabase($manager, $generatorConfig, $appDatabase, $connections, $debugLogger);

            if ($database) {
                $reversedSchema->addDatabase($database);
            }
            $totalNbTables += $nbTables;
        }

        if ($totalNbTables) {
            Logger::log(sprintf('%d tables found in all databases.', $totalNbTables));
        } else {
            Logger::log('No table found in all databases');
        }

        // Comparing models with the connected database for this module
        Logger::log('Comparing models...');
        $migrationsUp = [];
        $migrationsDown = [];
        $configManager = new ConfigurationManager($generatorConfig->getSection('paths')['phpConfDir']);
        $excludedTables = (array)$configManager->getSection('exclude_tables');

        foreach ($reversedSchema->getDatabases() as $database) {
            $name = $database->getName();

            if ($debugLogger) {
                Logger::log(sprintf('Comparing database "%s"', $name));
            }

            $appDataDatabase = $manager->getDatabase($name);
            if (!$appDataDatabase) {
                Logger::log(sprintf('<error>Database "%s" does not exist in schema.xml. Skipped.</error>', $name));
                continue;
            }

            $databaseDiff = DatabaseComparator::computeDiff($database, $appDataDatabase, true, false, false, $excludedTables);

            if (!$databaseDiff) {
                if ($debugLogger) {
                    Logger::log(sprintf('Same XML and database structures for datasource "%s" - no diff to generate', $name));
                }
                continue;
            }

            list(, $platform) = $migrationService->getPlatformAndConnection($manager, $name, $generatorConfig);
            $migrationsUp[$name] = $platform->getModifyDatabaseDDL($databaseDiff);
            $migrationsDown[$name] = $platform->getModifyDatabaseDDL($databaseDiff->getReverseDiff());
        }
        if (count($migrationsUp) === 0) {
            Logger::log('Same XML and database structures for all datasource - no diff to generate');
            return true;
        }

        $migrationService->generateMigrationFile($manager, $migrationsUp, $migrationsDown, $generatorConfig);
        return true;
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @param string $apiClass
     * @return boolean
     * @throws \ReflectionException
     */
    private function generateBaseApiTemplate($module, $modPath, $force = false, $apiClass = "")
    {
        $created = true;
        $modelPath = $modPath . $module . DIRECTORY_SEPARATOR . 'Models';
        $apiPath = $modPath . $module . DIRECTORY_SEPARATOR . 'Api';
        if (file_exists($modelPath)) {
            $dir = dir($modelPath);
            $this->generateApiFiles($module, $force, $apiClass, $dir, $apiPath);
        }
        return $created;
    }

    /**
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generateConfigTemplate($modPath, $force = false)
    {
        //Generamos el fichero de configuración
        Logger::log("Generamos fichero vacío de configuración");
        return $this->writeTemplateToFile("<?php\n\t",
            $modPath . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "config.php",
            $force);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generateSchemaTemplate($module, $modPath, $force = false)
    {
        //Generamos el autoloader del módulo
        Logger::log("Generamos el schema");
        $schema = $this->tpl->dump("generator/schema.propel.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '', $module),
            "prefix" => preg_replace('/(\\\|\/)/', '', $module),
            "db" => $this->config->get("db_name"),
        ));
        return $this->writeTemplateToFile($schema,
            $modPath . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml",
            $force);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generatePropertiesTemplate($module, $modPath, $force = false)
    {
        Logger::log("Generamos la configuración de Propel");
        $buildProperties = $this->tpl->dump("generator/build.properties.twig", array(
            "module" => $module,
            "namespace" => preg_replace('/(\\\|\/)/', '', $module),
        ));
        return $this->writeTemplateToFile($buildProperties,
            $modPath . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.php",
            $force);
    }

    /**
     * @param string $module
     * @param string $modPath
     * @param boolean $force
     * @return boolean
     */
    private function generateIndexTemplate($module, $modPath, $force = false)
    {
        //Generamos la plantilla de index
        Logger::log("Generamos una plantilla base por defecto");
        $index = $this->tpl->dump("generator/index.template.twig", array(
            "module" => $module,
        ));
        return $this->writeTemplateToFile($index,
            $modPath . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig",
            $force);
    }

    /**
     * Create ApiBase
     * @param string $module
     * @param string $modPath
     * @param string $api
     * @param string $apiClass
     * @param string $package
     *
     * @return bool
     */
    private function createApiBaseFile($module, $modPath, $api, $apiClass = '', $package = null)
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
            'package' => $package,
        ));

        return $this->writeTemplateToFile($controller,
            $modPath . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . "{$api}BaseApi.php", true);
    }

    /**
     * Create Api
     * @param string $module
     * @param string $modPath
     * @param bool $force
     * @param string $api
     * @param string $package
     *
     * @return bool
     */
    private function createApi($module, $modPath, $force, $api, $package = null)
    {
        $class = preg_replace('/(\\\|\/)/', '', $module);
        $controller = $this->tpl->dump("generator/api.template.twig", array(
            "module" => $module,
            "api" => $api,
            "namespace" => preg_replace('/(\\\|\/)/', '\\', $module),
            "url" => preg_replace('/(\\\|\/)/', '/', $module),
            "class" => $class,
            "package" => $package,
        ));

        return $this->writeTemplateToFile($controller, $modPath . DIRECTORY_SEPARATOR . "{$api}.php", $force);
    }

    /**
     * @param $module
     * @param $force
     * @param $apiClass
     * @param \Directory|null $dir
     * @param string $apiPath
     * @param string $package
     * @throws \ReflectionException
     */
    private function generateApiFiles($module, $force, $apiClass, \Directory $dir, string $apiPath, $package = null)
    {
        $base = $dir->path;
        while ($file = $dir->read()) {
            if (!in_array(strtolower($file), ['.', '..', 'base', 'map'])) {
                if (is_dir($base . DIRECTORY_SEPARATOR . $file)) {
                    $this->generateApiFiles($module, $force, $apiClass, dir($base . DIRECTORY_SEPARATOR . $file), $apiPath . DIRECTORY_SEPARATOR . $file, $file);
                } else if (!preg_match('/Query\.php$/i', $file)
                    && !preg_match('/I18n\.php$/i', $file)
                    && preg_match('/\.php$/i', $file)
                ) {
                    $filename = str_replace(".php", "", $file);
                    $this->log->addLog("Generamos Api BASES para {$filename}");
                    if ($this->checkIfIsModel($module, $filename, $package)) {
                        $this->createApiBaseFile($module, $apiPath, $filename, $apiClass, $package);
                        $this->createApi($module, $apiPath, $force, $filename, $package);
                    }
                }
            }
        }
    }

    /**
     * @param string $module
     * @param string $package
     * @param string $filename
     * @return bool
     * @throws \ReflectionException
     */
    private function checkIfIsModel($module, $filename, $package = null)
    {
        $parts = [$module, 'Models'];
        if (strlen($package ?: '')) {
            $parts[] = $package;
        }
        $parts[] = $filename;
        $namespace = '\\' . implode('\\', $parts);
        $reflectorClass = new \ReflectionClass($namespace);
        $isModel = $reflectorClass->isInstantiable();
        return $isModel;
    }
}
