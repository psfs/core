<?php

namespace PSFS\tests\services;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Schema;
use PSFS\base\SingletonRegistry;
use PSFS\base\exception\ApiException;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\traits\BoostrapTrait;
use PSFS\services\GeneratorService as Service;
use PSFS\services\MigrationService;

/**
 * Class GeneratorServiceTest
 * @package PSFS\tests\services
 */
class GeneratorServiceTest extends TestCase
{
    use BoostrapTrait;

    const MODULE_NAME = 'CLIENT';

    protected function tearDown(): void
    {
        MigrationService::dropInstance();
    }

    public function testBaseClass()
    {
        $this->assertTrue(true);
    }

    public function testBuildReversedSchemaAddsOnlyDatabasesReturnedByMigrationService(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);
        $manager = $this->createMock(MigrationManager::class);
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $migrationService = $this->createMock(MigrationService::class);

        $appPrimary = new Database('primary');
        $appSecondary = new Database('secondary');
        $manager->method('getDatabases')->willReturn([$appPrimary, $appSecondary]);
        $generatorConfig->method('getBuildConnections')->willReturn([]);

        $reversedPrimary = new Database('primary');
        $calls = 0;
        $migrationService->expects($this->exactly(2))
            ->method('checkSourceDatabase')
            ->willReturnCallback(
                static function (
                    MigrationManager $actualManager,
                    GeneratorConfig $actualConfig,
                    Database $actualAppDatabase,
                    array $actualConnections,
                    bool $debugLogger
                ) use (
                    $manager,
                    $generatorConfig,
                    $appPrimary,
                    $appSecondary,
                    $reversedPrimary,
                    &$calls
                ): array {
                    TestCase::assertSame($manager, $actualManager);
                    TestCase::assertSame($generatorConfig, $actualConfig);
                    TestCase::assertSame([], $actualConnections);
                    TestCase::assertTrue($debugLogger);
                    $calls++;

                    if (1 === $calls) {
                        TestCase::assertSame($appPrimary, $actualAppDatabase);
                        return [$reversedPrimary, 2];
                    }

                    TestCase::assertSame($appSecondary, $actualAppDatabase);
                    return [null, 0];
                }
            );

        /** @var Schema $schema */
        $schema = $this->invokePrivateMethod(
            $service,
            'buildReversedSchema',
            [$manager, $generatorConfig, $migrationService, true]
        );

        $databases = $schema->getDatabases();
        $this->assertCount(1, $databases);
        $this->assertSame('primary', $databases[0]->getName());
    }

    public function testBuildMigrationDiffsSkipsMissingDatabaseAndNoDiffAndBuildsUpDownForChanges(): void
    {
        $service = $this->newServiceWithoutConstructor(GeneratorServiceTestDouble::class);
        $service->excludedTables = ['skip_me'];
        $service->diffsByName = [
            'with_diff' => new GeneratorServiceDiffStub('reverse-with-diff'),
            'without_diff' => false,
        ];

        $manager = $this->createMock(MigrationManager::class);
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $migrationService = $this->createMock(MigrationService::class);

        $databaseWithDiff = new Database('with_diff');
        $databaseWithoutDiff = new Database('without_diff');
        $databaseMissingInSchema = new Database('missing_schema');
        $reversedSchema = new Schema();
        $reversedSchema->addDatabase($databaseWithDiff);
        $reversedSchema->addDatabase($databaseWithoutDiff);
        $reversedSchema->addDatabase($databaseMissingInSchema);

        $schemaDatabaseWithDiff = new Database('with_diff');
        $schemaDatabaseWithoutDiff = new Database('without_diff');
        $manager->method('getDatabase')->willReturnCallback(
            static function (string $name) use ($schemaDatabaseWithDiff, $schemaDatabaseWithoutDiff): ?Database {
                return match ($name) {
                    'with_diff' => $schemaDatabaseWithDiff,
                    'without_diff' => $schemaDatabaseWithoutDiff,
                    default => null,
                };
            }
        );

        $platform = new class {
            public function getModifyDatabaseDDL($diff): string
            {
                return 'ddl:' . (is_string($diff) ? $diff : 'with-diff');
            }
        };
        $migrationService->expects($this->once())
            ->method('getPlatformAndConnection')
            ->with($manager, 'with_diff', $generatorConfig)
            ->willReturn([null, $platform]);

        [$up, $down] = $this->invokePrivateMethod(
            $service,
            'buildMigrationDiffs',
            [$manager, $generatorConfig, $migrationService, $reversedSchema, false]
        );

        $this->assertSame(['with_diff' => 'ddl:with-diff'], $up);
        $this->assertSame(['with_diff' => 'ddl:reverse-with-diff'], $down);
        $this->assertSame(
            ['with_diff' => ['skip_me'], 'without_diff' => ['skip_me']],
            $service->capturedExcludedTablesByDatabase
        );
    }

    public function testBuildReversedSchemaLogsWhenNoTablesWereDetected(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);
        $manager = $this->createMock(MigrationManager::class);
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $migrationService = $this->createMock(MigrationService::class);

        $manager->method('getDatabases')->willReturn([new Database('empty')]);
        $generatorConfig->method('getBuildConnections')->willReturn([]);
        $migrationService->method('checkSourceDatabase')->willReturn([null, 0]);

        $schema = $this->invokePrivateMethod(
            $service,
            'buildReversedSchema',
            [$manager, $generatorConfig, $migrationService, false]
        );

        $this->assertCount(0, $schema->getDatabases());
    }

    public function testBuildMigrationDiffsWithDebugLogsNoDiffBranch(): void
    {
        $service = $this->newServiceWithoutConstructor(GeneratorServiceTestDouble::class);
        $service->excludedTables = ['skip_me'];
        $service->diffsByName = ['without_diff' => false];

        $manager = $this->createMock(MigrationManager::class);
        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $migrationService = $this->createMock(MigrationService::class);

        $schemaDatabaseWithoutDiff = new Database('without_diff');
        $manager->method('getDatabase')->willReturn($schemaDatabaseWithoutDiff);

        $reversedSchema = new Schema();
        $reversedSchema->addDatabase(new Database('without_diff'));

        [$up, $down] = $this->invokePrivateMethod(
            $service,
            'buildMigrationDiffs',
            [$manager, $generatorConfig, $migrationService, $reversedSchema, true]
        );

        $this->assertSame([], $up);
        $this->assertSame([], $down);
    }

    public function testComputeDatabaseDiffCanBeInvokedWithExcludedTables(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);
        $database = new Database('source');
        $target = new Database('target');
        $method = new \ReflectionMethod(Service::class, 'computeDatabaseDiff');
        $method->setAccessible(true);

        $diff = $method->invoke($service, $database, $target, ['skip_me']);

        $this->assertTrue(is_object($diff) || $diff === false);
    }

    public function testGenerateControllerTemplateCreatesDedicatedTestFileWhenMissing(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);
        $templateReflection = new \ReflectionClass(GeneratorTemplateStub::class);
        $this->setObjectProperty($service, 'tpl', $templateReflection->newInstanceWithoutConstructor());

        $modulePath = CORE_DIR . DIRECTORY_SEPARATOR . 'GENERATOR_TEMPLATE_COVERAGE';
        @mkdir($modulePath . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'base', 0775, true);
        @mkdir($modulePath . DIRECTORY_SEPARATOR . 'Test', 0775, true);
        $testFile = $modulePath . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'DemoTest.php';
        @unlink($testFile);

        $method = new \ReflectionMethod(Service::class, 'generateControllerTemplate');
        $method->setAccessible(true);
        $created = $method->invoke($service, 'Demo', $modulePath, true, 'normal');

        $this->assertTrue((bool)$created);
        $this->assertFileExists($testFile);
        GeneratorHelper::deleteDir($modulePath);
    }

    public function testCreateModuleMigrationsThrowsApiExceptionWhenPendingMigrationsExist(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);
        $manager = $this->createMock(MigrationManager::class);
        $manager->method('hasPendingMigrations')->willReturn(true);
        $generatorConfig = $this->createMock(GeneratorConfig::class);

        $migrationService = $this->getMockBuilder(MigrationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnectionManager'])
            ->getMock();
        $migrationService->method('getConnectionManager')->willReturn([$manager, $generatorConfig]);
        $this->injectSingleton(MigrationService::class, $migrationService);

        $method = new \ReflectionMethod(Service::class, 'createModuleMigrations');
        $method->setAccessible(true);

        $this->expectException(ApiException::class);
        $method->invoke($service, 'Demo', CORE_DIR . DIRECTORY_SEPARATOR);
    }

    public function testCreateModuleMigrationsReturnsTrueWhenNoDiffIsFound(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);

        $manager = $this->createMock(MigrationManager::class);
        $manager->method('hasPendingMigrations')->willReturn(false);
        $manager->method('getDatabases')->willReturn([]);

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $generatorConfig->method('getBuildConnections')->willReturn([]);
        $generatorConfig->method('getSection')->willReturn([
            'phpConfDir' => CORE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'propel' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'bookstore',
        ]);

        $migrationService = $this->getMockBuilder(MigrationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnectionManager', 'generateMigrationFile'])
            ->getMock();
        $migrationService->method('getConnectionManager')->willReturn([$manager, $generatorConfig]);
        $migrationService->expects($this->never())->method('generateMigrationFile');
        $this->injectSingleton(MigrationService::class, $migrationService);

        $method = new \ReflectionMethod(Service::class, 'createModuleMigrations');
        $method->setAccessible(true);
        $result = $method->invoke($service, 'Demo', CORE_DIR . DIRECTORY_SEPARATOR);

        $this->assertTrue((bool)$result);
    }

    public function testCreateModuleMigrationsGeneratesFileWhenDiffExists(): void
    {
        $service = $this->newServiceWithoutConstructor(Service::class);

        $manager = $this->createMock(MigrationManager::class);
        $manager->method('hasPendingMigrations')->willReturn(false);
        $appDatabase = new Database('bookstore');
        $manager->method('getDatabases')->willReturn([$appDatabase]);
        $manager->method('getDatabase')->with('bookstore')->willReturn(new Database('bookstore'));

        $generatorConfig = $this->createMock(GeneratorConfig::class);
        $generatorConfig->method('getBuildConnections')->willReturn([]);
        $generatorConfig->method('getSection')->willReturn([
            'phpConfDir' => CORE_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'propel' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'bookstore',
        ]);

        $platform = new class {
            public function getModifyDatabaseDDL($diff): string
            {
                return is_string($diff) ? $diff : 'ddl-up';
            }
        };
        $diff = new GeneratorServiceDiffStub('ddl-down');

        $migrationService = $this->getMockBuilder(MigrationService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnectionManager', 'checkSourceDatabase', 'getPlatformAndConnection', 'generateMigrationFile'])
            ->getMock();
        $migrationService->method('getConnectionManager')->willReturn([$manager, $generatorConfig]);
        $migrationService->method('checkSourceDatabase')->willReturn([new Database('bookstore'), 1]);
        $migrationService->method('getPlatformAndConnection')->willReturn([null, $platform]);
        $migrationService->expects($this->once())
            ->method('generateMigrationFile')
            ->with($manager, ['bookstore' => 'ddl-up'], ['bookstore' => 'ddl-down'], $generatorConfig, 'Demo');
        $this->injectSingleton(MigrationService::class, $migrationService);

        $serviceDouble = $this->newServiceWithoutConstructor(GeneratorServiceTestDouble::class);
        $serviceDouble->excludedTables = [];
        $serviceDouble->diffsByName = ['bookstore' => $diff];
        $method = new \ReflectionMethod(Service::class, 'createModuleMigrations');
        $method->setAccessible(true);
        $result = $method->invoke($serviceDouble, 'Demo', CORE_DIR . DIRECTORY_SEPARATOR);

        $this->assertTrue((bool)$result);
    }

    public static array $filesToCheckWithoutSchema = [
        DIRECTORY_SEPARATOR . 'phpunit.xml.dist',
        DIRECTORY_SEPARATOR . 'autoload.php',
        DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Test.php',
        DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'index.html.twig',
        DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Service.php',
        DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Controller.php',
        DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'BaseController.php',
        DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js',
        DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'styles.css',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'schema.xml',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'propel.php',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'config.php',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql' . DIRECTORY_SEPARATOR . 'CLIENT.sql',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql' . DIRECTORY_SEPARATOR . 'sqldb.map',
    ];

    public static array $filesToCheckWithSchema = [
        // Simple model creation
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'SoloBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'SoloQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'SoloQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'SoloTableMap.php',
        // Package model creation and I18n
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'TestBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestI18n.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestI18nQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestI18n.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestI18nQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'TestTableMap.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'TestI18nTableMap.php',
        // Package related model
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'RelatedBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'RelatedQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'RelatedQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'RelatedTableMap.php',
    ];

    /**
     * @param Service $generatorService
     * @throws \PSFS\base\exception\GeneratorException
     * @throws \ReflectionException
     */
    public function createNewModule(Service $generatorService): void
    {
        $generatorService->createStructureModule(self::MODULE_NAME, true, skipMigration: true);
        $this->checkBasicStructure();
    }

    /**
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     * @throws \ReflectionException
     */
    public function checkCreateExistingModule(): string
    {
        $generatorService = Service::getInstance();
        $this->assertInstanceOf(Service::class, $generatorService, 'Error getting GeneratorService instance');
        $modulePath = CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE_NAME;

        $this->createNewModule($generatorService);

        GeneratorHelper::copyr(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'Config',
            $modulePath . DIRECTORY_SEPARATOR . 'Config'
        );
        require_once $modulePath . DIRECTORY_SEPARATOR . 'autoload.php';
        $generatorService->createStructureModule(self::MODULE_NAME, skipMigration: true);
        $this->checkBasicStructure();

        foreach (self::$filesToCheckWithSchema as $fileName) {
            $this->assertFileExists($modulePath . $fileName, $fileName . ' do not exists after generate module with schema');
        }
        return $modulePath;
    }

    private function checkBasicStructure()
    {
        $modulePath = CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE_NAME;
        $this->assertDirectoryExists($modulePath, 'Directory not created');
        foreach (self::$filesToCheckWithoutSchema as $fileName) {
            $this->assertFileExists($modulePath . $fileName, $fileName . ' do not exists after generate module from scratch');
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function newServiceWithoutConstructor(string $className): object
    {
        $reflection = new \ReflectionClass($className);
        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @param object $instance
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    private function invokePrivateMethod(object $instance, string $method, array $arguments = [])
    {
        $reflectionMethod = new \ReflectionMethod(Service::class, $method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($instance, $arguments);
    }

    private function setObjectProperty(object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($target, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($target, $value);
    }

    private function injectSingleton(string $class, object $instance): void
    {
        $reflection = new \ReflectionProperty(SingletonRegistry::class, 'instances');
        $reflection->setAccessible(true);
        $instances = $reflection->getValue();
        $context = $_SERVER[SingletonRegistry::CONTEXT_SESSION] ?? SingletonRegistry::CONTEXT_SESSION;
        if (!isset($instances[$context]) || !is_array($instances[$context])) {
            $instances[$context] = [];
        }
        $instances[$context][$class] = $instance;
        $reflection->setValue(null, $instances);
    }
}

final class GeneratorTemplateStub extends \PSFS\base\Template
{
    public function dump($template, $vars = array(), bool $disableCache = false): string
    {
        return (string)$template . '::' . ($vars['class'] ?? 'class');
    }
}

final class GeneratorServiceDiffStub
{
    public function __construct(private string $reverseDiff)
    {
    }

    public function getReverseDiff(): string
    {
        return $this->reverseDiff;
    }
}

final class GeneratorServiceTestDouble extends Service
{
    /** @var array<int, string> */
    public array $excludedTables = [];
    /** @var array<string, mixed> */
    public array $diffsByName = [];
    /** @var array<string, array<int, string>> */
    public array $capturedExcludedTablesByDatabase = [];

    protected function resolveExcludedTables(GeneratorConfig $generatorConfig): array
    {
        return $this->excludedTables;
    }

    protected function computeDatabaseDiff(Database $database, Database $appDataDatabase, array $excludedTables)
    {
        $name = $database->getName();
        $this->capturedExcludedTablesByDatabase[$name] = $excludedTables;
        return $this->diffsByName[$name] ?? false;
    }
}
