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

        [$returnedManager, $returnedConfig] = $service->getConnectionManager(
            'client',
            CORE_DIR . DIRECTORY_SEPARATOR
        );

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
        $manager = $this->createMock(MigrationManager::class);
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $appDatabase = new Database('main');

        [$database, $tables] = $service->checkSourceDatabase($manager, $generatorConfig, $appDatabase, [], true);

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

    public function testGenerateMigrationFileUsesDeterministicTimestampAndWritesFile(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'migration_test_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $manager = $this->createMock(MigrationManager::class);
        $manager->expects($this->once())->method('getMigrationFileName')->with(12345)->willReturn('Version12345.php');
        $manager->expects($this->once())
            ->method('getMigrationClassBody')
            ->with(['up'], ['down'], 12345)
            ->willReturn('<?php echo "migration";');

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $generatorConfig->method('getSection')->with('paths')->willReturn(['migrationDir' => $tmpDir]);

        $service = new class extends MigrationService {
            public function __construct()
            {
            }
            protected function getCurrentTimestamp(): int
            {
                return 12345;
            }
        };

        $service->generateMigrationFile($manager, ['up'], ['down'], $generatorConfig);
        $file = $tmpDir . DIRECTORY_SEPARATOR . 'Version12345.php';

        $this->assertFileExists($file);
        $this->assertSame('<?php echo "migration";', (string)file_get_contents($file));
        @unlink($file);
        @rmdir($tmpDir);
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
