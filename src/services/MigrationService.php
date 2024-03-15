<?php
namespace PSFS\services;

use Propel\Generator\Manager\MigrationManager;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Generator\PropelHelperTrait;

class MigrationService extends SimpleService {
    use PropelHelperTrait;


    /**
     * @param string $module
     * @param string $path
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function getConnectionManager(string $module, string $path): array {
        $modulePath = str_replace(CORE_DIR . DIRECTORY_SEPARATOR, '', $path . $module);
        $generatorConfig = $this->getConfigGenerator($modulePath);

        $manager = new MigrationManager();
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas(
            $generatorConfig->getSection('paths')['schemaDir'],
            $generatorConfig->getSection('generator')['recursive'])
        );
        $connections = $generatorConfig->getBuildConnections();
        $manager->setConnections($connections);
        $manager->setMigrationTable($generatorConfig->getConfigProperty('migrations.tableName'));
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['migrationDir']);
        return [$manager, $generatorConfig];
    }
}
