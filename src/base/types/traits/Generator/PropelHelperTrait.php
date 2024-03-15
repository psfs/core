<?php
namespace PSFS\base\types\traits\Generator;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\AbstractManager;
use Propel\Generator\Manager\ModelManager;
use Propel\Generator\Manager\SqlManager;
use PSFS\base\Logger;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
    private function getPropelPaths(string $modulePath): array
    {
        $moduleDir = CORE_DIR . DIRECTORY_SEPARATOR . $modulePath;
        GeneratorHelper::createDir($moduleDir);
        $moduleDir = realpath($moduleDir);
        $configDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config';
        $sqlDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql';
        $migrationDir = $moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations';
        return [
            'projectDir' => $moduleDir,
            'outputDir' => $moduleDir,
            'phpDir' => $moduleDir,
            'phpConfDir' => $configDir,
            'sqlDir' => $sqlDir,
            'migrationDir' => $migrationDir,
            'schemaDir' => $configDir,
        ];
    }

    /**
     * @param string $modulePath
     * @return GeneratorConfig
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function getConfigGenerator(string $modulePath): GeneratorConfig
    {
        // Generate the configurator
        $paths = $this->getPropelPaths($modulePath);
        foreach ($paths as $path) {
            GeneratorHelper::createDir($path);
        }
        return new GeneratorConfig($paths['phpConfDir'], [
            'propel' => [
                'paths' => $paths,
            ]
        ]);
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
    private function buildSql(GeneratorConfig $configGenerator): void
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
    private function setupManager(GeneratorConfig $configGenerator, AbstractManager &$manager, string $workingDir = CORE_DIR): void
    {
        $manager->setGeneratorConfig($configGenerator);
        $schemaFile = new \SplFileInfo($configGenerator->getSection('paths')['schemaDir'] . DIRECTORY_SEPARATOR . 'schema.xml');
        $manager->setSchemas([$schemaFile]);
        $manager->setLoggerClosure(function ($message) {
            Logger::log($message, LOG_INFO);
        });
        $manager->setWorkingDirectory($workingDir);
    }

    /**
     * Find every schema files.
     *
     * @param string|array<string> $directory Path to the input directory
     * @param bool $recursive Search for file inside the input directory and all subdirectories
     *
     * @return array List of schema files
     */
    protected function getSchemas(array|string $directory, bool $recursive = false): array
    {
        $finder = new Finder();
        $finder
            ->name('*schema.xml')
            ->sortByName()
            ->in($directory);
        if (!$recursive) {
            $finder->depth(0);
        }

        return iterator_to_array($finder->files());
    }
}
