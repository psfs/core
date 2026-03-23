<?php

namespace PSFS\services;

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\IdMethod;
use PSFS\base\Logger;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Generator\PropelHelperTrait;
use PSFS\services\migration\CommandRunner;
use PSFS\services\migration\MigrationEngineResolver;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\migration\PhinxConfigFactory;
use PSFS\services\migration\PhinxMigrationEngine;
use PSFS\services\migration\PropelMigrationEngine;
use PSFS\services\migration\SqlStatementSplitter;
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
        $generatorConfig = $this->resolveGeneratorConfig($modulePath);

        $manager = $this->createMigrationManager();
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas(
            $this->getSchemas(
                $generatorConfig->getSection('paths')['schemaDir'],
                $generatorConfig->getSection('generator')['recursive']
            )
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
    public function checkSourceDatabase(
        MigrationManager $manager,
        GeneratorConfig $generatorConfig,
        Database $appDatabase,
        array $connections,
        bool $debugLogger = false
    ): array {
        $name = $appDatabase->getName();
        $params = $connections[$name] ?? [];
        if (!$params) {
            Logger::log(sprintf('<info>No connection configured for database "%s"</info>', $name));
        }

        if ($debugLogger) {
            Logger::log(sprintf('Connecting to database "%s" using DSN "%s"', $name, (string)($params['dsn'] ?? '')));
        }

        list($conn, $platform) = $this->getPlatformAndConnection($manager, $name, $generatorConfig);

        $appDatabase->setPlatform($platform);

        if ($platform && !$platform->supportsMigrations()) {
            Logger::log(
                sprintf(
                    'Skipping database "%s" since vendor "%s" does not support migrations',
                    $name,
                    $platform->getDatabaseType()
                )
            );
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
    public function getPlatformAndConnection(
        MigrationManager $manager,
        ?string $name,
        GeneratorConfig $generatorConfig
    ): array {
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
    public function generateMigrationFile(
        MigrationManager $manager,
        array $migrationsUp,
        array $migrationsDown,
        GeneratorConfig $generatorConfig,
        ?string $module = null,
        ?string $engineName = null
    ): void {
        $timestamp = $this->getCurrentTimestamp();
        $migrationDir = $generatorConfig->getSection('paths')['migrationDir'];
        $resolvedModule = $module ?: $this->resolveModuleFromMigrationDir($migrationDir);
        $engine = $this->createMigrationEngineResolver()->resolve($engineName, strtolower($resolvedModule));
        $result = $engine->generateFromDiff(
            $resolvedModule,
            $migrationsUp,
            $migrationsDown,
            $migrationDir,
            $timestamp,
            $manager
        );

        if (!$result->isSuccess()) {
            throw new \RuntimeException($result->getOutput());
        }
        Logger::log($result->getOutput());
    }

    public function runMigrate(string $module, string $moduleBasePath, bool $simulate = false, ?string $engineName = null): MigrationExecutionResult
    {
        $context = $this->buildExecutionContext($module, $moduleBasePath, $simulate);
        $engine = $this->createMigrationEngineResolver()->resolve($engineName, strtolower($module));
        return $engine->migrate($context);
    }

    public function runRollback(string $module, string $moduleBasePath, bool $simulate = false, ?string $engineName = null): MigrationExecutionResult
    {
        $context = $this->buildExecutionContext($module, $moduleBasePath, $simulate);
        $engine = $this->createMigrationEngineResolver()->resolve($engineName, strtolower($module));
        return $engine->rollback($context);
    }

    public function runStatus(string $module, string $moduleBasePath, ?string $engineName = null): MigrationExecutionResult
    {
        $context = $this->buildExecutionContext($module, $moduleBasePath, false);
        $engine = $this->createMigrationEngineResolver()->resolve($engineName, strtolower($module));
        return $engine->status($context);
    }

    protected function createMigrationManager(): MigrationManager
    {
        return new MigrationManager();
    }

    protected function resolveGeneratorConfig(string $modulePath): GeneratorConfig
    {
        return $this->getConfigGenerator($modulePath);
    }

    protected function getCurrentTimestamp(): int
    {
        return time();
    }

    protected function writeFile(string $file, string $content): void
    {
        file_put_contents($file, $content);
    }

    protected function createMigrationEngineResolver(): MigrationEngineResolver
    {
        $runner = $this->createCommandRunner();
        $propel = new PropelMigrationEngine($runner);
        $phinx = new PhinxMigrationEngine(
            $runner,
            new PhinxConfigFactory(),
            new SqlStatementSplitter()
        );

        return new MigrationEngineResolver($phinx, $propel);
    }

    protected function createCommandRunner(): CommandRunner
    {
        return new CommandRunner();
    }

    private function resolveModuleFromMigrationDir(string $migrationDir): string
    {
        $module = basename(dirname(dirname($migrationDir)));
        return '' !== $module ? $module : 'module';
    }

    private function buildExecutionContext(string $module, string $moduleBasePath, bool $simulate): MigrationExecutionContext
    {
        $moduleRoot = realpath($moduleBasePath);
        if (false === $moduleRoot) {
            throw new \RuntimeException(sprintf('Invalid module base path: %s', $moduleBasePath));
        }
        $configDir = $moduleRoot . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            throw new \RuntimeException(sprintf('Module config directory not found: %s', $configDir));
        }
        $migrationDir = $configDir . DIRECTORY_SEPARATOR . 'Migrations';
        return new MigrationExecutionContext($module, $configDir, $migrationDir, $simulate);
    }
}
