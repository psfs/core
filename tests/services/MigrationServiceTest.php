<?php

namespace PSFS\tests\services;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Table;
use Propel\Generator\Platform\PlatformInterface;
use Propel\Generator\Reverse\SchemaParserInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use PSFS\services\MigrationService;
use PSFS\services\migration\MigrationEngineInterface;
use PSFS\services\migration\MigrationEngineResolver;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\MigrationExecutionResult;

class MigrationServiceTest extends TestCase
{
    public function testGetConnectionManagerBuildsConfiguredManager(): void
    {
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $manager = $this->createMock(MigrationManager::class);
        $paths = ['schemaDir' => '/tmp/schema', 'migrationDir' => '/tmp/migrations'];
        $generator = ['recursive' => true];
        $generatorConfig->method('getSection')->willReturnMap([
            ['paths', $paths],
            ['generator', $generator],
        ]);
        $generatorConfig->method('getBuildConnections')->willReturn(['main' => ['dsn' => 'sqlite::memory:']]);
        $generatorConfig->method('getConfigProperty')->with('migrations.tableName')->willReturn('propel_migration');

        $manager->expects($this->once())->method('setGeneratorConfig')->with($generatorConfig);
        $manager->expects($this->once())->method('setSchemas')->with(['schema.xml']);
        $manager->expects($this->once())->method('setConnections')->with(['main' => ['dsn' => 'sqlite::memory:']]);
        $manager->expects($this->once())->method('setMigrationTable')->with('propel_migration');
        $manager->expects($this->once())->method('setWorkingDirectory')->with('/tmp/migrations');

        $service = new class($manager, $generatorConfig) extends MigrationService {
            public string $capturedModulePath = '';

            public function __construct(private MigrationManager $managerMock, private GeneratorConfig $configMock)
            {
            }

            protected function createMigrationManager(): MigrationManager
            {
                return $this->managerMock;
            }

            protected function resolveGeneratorConfig(string $modulePath): GeneratorConfig
            {
                $this->capturedModulePath = (string)$modulePath;
                return $this->configMock;
            }

            public function getSchemas(array|string $path, bool $recursive = false): array
            {
                return ['schema.xml'];
            }
        };

        [$returnedManager, $returnedConfig] = $service->getConnectionManager('client', CORE_DIR . DIRECTORY_SEPARATOR);

        $this->assertSame('client', $service->capturedModulePath);
        $this->assertSame($manager, $returnedManager);
        $this->assertSame($generatorConfig, $returnedConfig);
    }

    public function testCheckSourceDatabaseSkipsUnsupportedPlatforms(): void
    {
        $service = new class extends MigrationService {
            public PlatformInterface $platform;
            public ConnectionInterface $connection;

            public function __construct()
            {
            }

            public function getPlatformAndConnection(
                MigrationManager $manager,
                ?string $name,
                GeneratorConfig $generatorConfig
            ): array {
                return [$this->connection, $this->platform];
            }
        };
        $service->connection = $this->createStub(ConnectionInterface::class);
        $service->platform = $this->createStub(PlatformInterface::class);
        $service->platform->method('supportsMigrations')->willReturn(false);
        $service->platform->method('getDatabaseType')->willReturn('sqlite');

        [$database, $tables] = $service->checkSourceDatabase(
            $this->createMock(MigrationManager::class),
            $this->createMock(GeneratorConfig::class),
            new Database('main'),
            [],
            true
        );

        $this->assertNull($database);
        $this->assertSame(0, $tables);
    }

    public function testCheckSourceDatabaseParsesWithAdditionalTablesFromOtherSchemas(): void
    {
        $parser = $this->createMock(SchemaParserInterface::class);
        $parser->expects($this->once())
            ->method('parse')
            ->with(
                $this->isInstanceOf(Database::class),
                $this->callback(static function (array $additionalTables): bool {
                    return count($additionalTables) === 1 && $additionalTables[0] instanceof Table;
                })
            )
            ->willReturn(7);

        $service = new class extends MigrationService {
            public PlatformInterface $platform;
            public ConnectionInterface $connection;

            public function __construct()
            {
            }

            public function getPlatformAndConnection(
                MigrationManager $manager,
                ?string $name,
                GeneratorConfig $generatorConfig
            ): array {
                return [$this->connection, $this->platform];
            }
        };
        $service->connection = $this->createStub(ConnectionInterface::class);
        $service->platform = $this->createStub(PlatformInterface::class);
        $service->platform->method('supportsMigrations')->willReturn(true);

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $generatorConfig->expects($this->once())
            ->method('getConfiguredSchemaParser')
            ->with($service->connection, 'main')
            ->willReturn($parser);

        $appDatabase = new Database('main');
        $appDatabase->setSchema('public');
        $sameSchemaTable = new Table('same_schema_table');
        $sameSchemaTable->setSchema('public');
        $otherSchemaTable = new Table('other_schema_table');
        $otherSchemaTable->setSchema('audit');
        $appDatabase->addTable($sameSchemaTable);
        $appDatabase->addTable($otherSchemaTable);

        [$database, $tables] = $service->checkSourceDatabase(
            $this->createMock(MigrationManager::class),
            $generatorConfig,
            $appDatabase,
            ['main' => ['dsn' => 'pgsql:host=db;dbname=main']],
            true
        );

        $this->assertInstanceOf(Database::class, $database);
        $this->assertSame('main', $database->getName());
        $this->assertSame('public', $database->getSchema());
        $this->assertSame(7, $tables);
    }

    public function testGenerateMigrationFileDelegatesToResolvedEngine(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'migration_test_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $engine = new TestMigrationEngine('propel');
        $resolver = $this->createMock(MigrationEngineResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with(null, $this->isType('string'))
            ->willReturn($engine);

        $service = new class($resolver) extends MigrationService {
            public function __construct(private MigrationEngineResolver $resolverMock)
            {
            }

            protected function createMigrationEngineResolver(): MigrationEngineResolver
            {
                return $this->resolverMock;
            }

            protected function getCurrentTimestamp(): int
            {
                return 12345;
            }
        };

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $generatorConfig->method('getSection')->with('paths')->willReturn(['migrationDir' => $tmpDir]);

        $manager = $this->createMock(MigrationManager::class);
        $service->generateMigrationFile($manager, ['up'], ['down'], $generatorConfig);

        $this->assertSame(1, $engine->generateCalls);
        $this->assertNotSame('', (string)$engine->lastGenerateModule);
        $this->assertSame($tmpDir, $engine->lastGenerateDir);
        $this->assertSame(12345, $engine->lastGenerateTimestamp);
        $this->assertSame($manager, $engine->lastGenerateManager);

        @rmdir($tmpDir);
    }

    public function testRunMigrateBuildsExecutionContextAndDelegatesToSelectedEngine(): void
    {
        $moduleDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'migration_module_' . uniqid('', true);
        mkdir($moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations', 0777, true);

        $engine = new TestMigrationEngine('phinx');
        $resolver = $this->createMock(MigrationEngineResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with('phinx', 'client')
            ->willReturn($engine);

        $service = new class($resolver) extends MigrationService {
            public function __construct(private MigrationEngineResolver $resolverMock)
            {
            }

            protected function createMigrationEngineResolver(): MigrationEngineResolver
            {
                return $this->resolverMock;
            }
        };

        $result = $service->runMigrate('Client', $moduleDir, true, 'phinx');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('phinx', $result->getEngine());
        $this->assertInstanceOf(MigrationExecutionContext::class, $engine->lastMigrateContext);
        $this->assertSame('Client', $engine->lastMigrateContext->getModule());
        $this->assertTrue($engine->lastMigrateContext->isSimulate());

        @rmdir($moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations');
        @rmdir($moduleDir . DIRECTORY_SEPARATOR . 'Config');
        @rmdir($moduleDir);
    }

    public function testRunRollbackAndStatusDelegateToEngine(): void
    {
        $moduleDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'migration_module_' . uniqid('', true);
        mkdir($moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations', 0777, true);

        $engine = new TestMigrationEngine('propel');
        $resolver = $this->createMock(MigrationEngineResolver::class);
        $calls = 0;
        $resolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnCallback(function (?string $requestedEngine, ?string $module) use ($engine, &$calls): MigrationEngineInterface {
                if (0 === $calls) {
                    TestCase::assertSame('propel', $requestedEngine);
                    TestCase::assertSame('client', $module);
                } else {
                    TestCase::assertNull($requestedEngine);
                    TestCase::assertSame('client', $module);
                }
                $calls++;
                return $engine;
            });

        $service = new class($resolver) extends MigrationService {
            public function __construct(private MigrationEngineResolver $resolverMock)
            {
            }

            protected function createMigrationEngineResolver(): MigrationEngineResolver
            {
                return $this->resolverMock;
            }
        };

        $rollback = $service->runRollback('Client', $moduleDir, false, 'propel');
        $status = $service->runStatus('Client', $moduleDir);

        $this->assertTrue($rollback->isSuccess());
        $this->assertTrue($status->isSuccess());
        $this->assertCount(1, $engine->rollbackContexts);
        $this->assertCount(1, $engine->statusContexts);
        $this->assertFalse($engine->rollbackContexts[0]->isSimulate());
        $this->assertFalse($engine->statusContexts[0]->isSimulate());

        @rmdir($moduleDir . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Migrations');
        @rmdir($moduleDir . DIRECTORY_SEPARATOR . 'Config');
        @rmdir($moduleDir);
    }

    public function testGetPlatformAndConnectionDelegatesToManagerAndGeneratorConfig(): void
    {
        $service = new class extends MigrationService {
            public function __construct()
            {
            }
        };

        $manager = $this->createMock(MigrationManager::class);
        $connection = $this->createStub(ConnectionInterface::class);
        $manager->expects($this->once())->method('getAdapterConnection')->with('main')->willReturn($connection);

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $platform = $this->createStub(PlatformInterface::class);
        $generatorConfig->expects($this->once())
            ->method('getConfiguredPlatform')
            ->with($connection, 'main')
            ->willReturn($platform);

        [$returnedConnection, $returnedPlatform] = $service->getPlatformAndConnection($manager, 'main', $generatorConfig);

        $this->assertSame($connection, $returnedConnection);
        $this->assertSame($platform, $returnedPlatform);
    }
}

final class TestMigrationEngine implements MigrationEngineInterface
{
    public int $generateCalls = 0;
    public ?string $lastGenerateModule = null;
    public ?string $lastGenerateDir = null;
    public ?int $lastGenerateTimestamp = null;
    public ?MigrationManager $lastGenerateManager = null;
    public ?MigrationExecutionContext $lastMigrateContext = null;
    /** @var array<int, MigrationExecutionContext> */
    public array $rollbackContexts = [];
    /** @var array<int, MigrationExecutionContext> */
    public array $statusContexts = [];

    public function __construct(private string $name, private bool $available = true)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function migrate(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $this->lastMigrateContext = $context;
        return MigrationExecutionResult::success($this->name, 'ok');
    }

    public function rollback(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $this->rollbackContexts[] = $context;
        return MigrationExecutionResult::success($this->name, 'ok');
    }

    public function status(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $this->statusContexts[] = $context;
        return MigrationExecutionResult::success($this->name, 'ok');
    }

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?MigrationManager $manager = null
    ): MigrationExecutionResult {
        $this->generateCalls++;
        $this->lastGenerateModule = $module;
        $this->lastGenerateDir = $migrationDir;
        $this->lastGenerateTimestamp = $timestamp;
        $this->lastGenerateManager = $manager;
        return MigrationExecutionResult::success($this->name, 'ok');
    }
}
