<?php

namespace PSFS\services;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\IdMethod;
use PSFS\base\Logger;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Generator\PropelHelperTrait;
use Symfony\Component\Console\Output\Output;

class MigrationService extends SimpleService
{
    use PropelHelperTrait;


    /**
     * @param string $module
     * @param string $path
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function getConnectionManager(string $module, string $path): array
    {
        $modulePath = str_replace(CORE_DIR . DIRECTORY_SEPARATOR, '', $path . $module);
        $generatorConfig = $this->getConfigGenerator($modulePath);

        $manager = new MigrationManager();
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas(
            $generatorConfig->getSection('paths')['schemaDir'],
            $generatorConfig->getSection('generator')['recursive'])
        );
        $manager->setConnections($generatorConfig->getBuildConnections());
        $manager->setMigrationTable($generatorConfig->getConfigProperty('migrations.tableName'));
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['migrationDir']);
        return [$manager, $generatorConfig];
    }

    /**
     * @param MigrationManager $manager
     * @param GeneratorConfig $generatorConfig
     * @param Database $appDatabase
     * @param array $connections
     * @param bool $debugLogger
     * @return array
     */
    public function checkSourceDatabase(MigrationManager $manager, GeneratorConfig $generatorConfig, Database $appDatabase, array $connections, bool $debugLogger = false): array
    {
        $name = $appDatabase->getName();
        $params = $connections[$name] ?? [];
        if (!$params) {
            Logger::log(sprintf('<info>No connection configured for database "%s"</info>', $name));
        }

        if ($debugLogger) {
            Logger::log(sprintf('Connecting to database "%s" using DSN "%s"', $name, $params['dsn']));
        }

        list($conn, $platform) = $this->getPlatformAndConnection($manager, $name, $generatorConfig);

        $appDatabase->setPlatform($platform);

        if ($platform && !$platform->supportsMigrations()) {
            Logger::log(sprintf('Skipping database "%s" since vendor "%s" does not support migrations', $name, $platform->getDatabaseType()));
            return [null, 0];
        }

        $additionalTables = [];
        foreach ($appDatabase->getTables() as $table) {
            if ($table->getSchema() && $table->getSchema() != $appDatabase->getSchema()) {
                $additionalTables[] = $table;
            }
        }

        $database = new Database($name);
        $database->setPlatform($platform);
        $database->setSchema($appDatabase->getSchema());
        $database->setDefaultIdMethod(IdMethod::NATIVE);

        $parser = $generatorConfig->getConfiguredSchemaParser($conn, $name);
        $nbTables = $parser->parse($database, $additionalTables);

        if ($debugLogger) {
            Logger::log(sprintf('%d tables found in database "%s"', $nbTables, $name), Output::VERBOSITY_VERBOSE);
        }
        return [$database, $nbTables];
    }

    /**
     * @param MigrationManager $manager
     * @param string|null $name
     * @param GeneratorConfig $generatorConfig
     * @return array
     */
    public function getPlatformAndConnection(MigrationManager $manager, ?string $name, GeneratorConfig $generatorConfig): array
    {
        $conn = $manager->getAdapterConnection($name);
        $platform = $generatorConfig->getConfiguredPlatform($conn, $name);
        return [$conn, $platform];
    }

    /**
     * @param MigrationManager $manager
     * @param array $migrationsUp
     * @param array $migrationsDown
     * @param GeneratorConfig $generatorConfig
     * @return void
     */
    public function generateMigrationFile(MigrationManager $manager, array $migrationsUp, array $migrationsDown, GeneratorConfig $generatorConfig): void
    {
        $timestamp = time();
        $migrationFileName = $manager->getMigrationFileName($timestamp);
        $migrationClassBody = $manager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp);

        $file = $generatorConfig->getSection('paths')['migrationDir'] . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($file, $migrationClassBody);

        Logger::log(sprintf('"%s" file successfully created.', $file));
    }
}
