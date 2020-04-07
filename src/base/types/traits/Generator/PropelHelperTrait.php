<?php
namespace PSFS\base\types\traits\Generator;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\AbstractManager;
use Propel\Generator\Manager\ModelManager;
use Propel\Generator\Manager\SqlManager;
use PSFS\base\Logger;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait PropelHelperTrait
 * @package PSFS\base\types\traits\Generator
 */
trait PropelHelperTrait {

    /**
     * @param string $modulePath
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function getPropelPaths($modulePath)
    {
        $moduleDir = CORE_DIR . DIRECTORY_SEPARATOR . $modulePath;
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
     * @param string $modulePath
     * @return GeneratorConfig
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function getConfigGenerator($modulePath)
    {
        // Generate the configurator
        $paths = $this->getPropelPaths($modulePath);
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
