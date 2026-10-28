<?php

namespace PSFS\tests\base\command\console;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\console\MigrateDatabaseCommand;
use PSFS\base\command\console\RollbackDatabaseMigrationCommand;
use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\MigrationService;
use Symfony\Component\Console\Tester\CommandTester;

class ModuleCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = CACHE_DIR . DIRECTORY_SEPARATOR . 'module-commands-' . uniqid('', true);
        mkdir($this->root . '/Shop/Config', 0777, true);
        mkdir($this->root . '/Backoffice/Config', 0777, true);
        mkdir($this->root . '/Static', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['Shop/Config', 'Backoffice/Config', 'Shop', 'Backoffice', 'Static', ''] as $directory) {
            @rmdir($this->root . ('' === $directory ? '' : '/' . $directory));
        }
    }

    public function testMigrateRunsEveryModuleWithTheNormalisedOptions(): void
    {
        $service = new RecordingMigrationService();
        $tester = new CommandTester(new MigrateWithService($service, $this->domains()));

        $exitCode = $tester->execute(['--simulate' => 'yes', '--engine' => 'propel', '--target' => '20261009123456']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertSame(
            [
                ['migrate', 'Shop', true, 'propel', 20261009123456],
                ['migrate', 'Backoffice', true, 'propel', 20261009123456],
            ],
            $service->calls
        );
        $this->assertStringContainsString('Migration completed', $tester->getDisplay());
    }

    public function testModuleOptionFiltersTheModulesAndModulesWithoutConfigAreSkipped(): void
    {
        $service = new RecordingMigrationService();
        $tester = new CommandTester(new MigrateWithService($service, $this->domains()));

        $this->assertSame(0, $tester->execute(['--module' => 'static']));

        $this->assertSame([], $service->calls);
        $this->assertStringContainsString('Module without DB configuration, skipping process for Static', $tester->getDisplay());
    }

    public function testAnInvalidTargetFailsTheModuleWithoutRunningTheMigration(): void
    {
        $service = new RecordingMigrationService();
        $tester = new CommandTester(new MigrateWithService($service, $this->domains()));

        $exitCode = $tester->execute(['--module' => 'shop', '--target' => 'latest']);

        $this->assertSame(1, $exitCode);
        $this->assertSame([], $service->calls);
        $this->assertStringContainsString('Migration target must be a non-negative integer version', $tester->getDisplay());
        $this->assertStringContainsString('Migration failed for: Shop (', $tester->getDisplay());
    }

    public function testRollbackReportsTheModulesWhoseEngineFailed(): void
    {
        $service = new RecordingMigrationService(false);
        $tester = new CommandTester(new RollbackWithService($service, $this->domains()));

        $exitCode = $tester->execute(['--module' => 'back']);

        $this->assertSame(1, $exitCode);
        $this->assertSame([['rollback', 'Backoffice', false, null, null]], $service->calls);
        $this->assertStringContainsString('Rollback failed for: Backoffice (phinx)', $tester->getDisplay());
    }

    public function testModuleNamesComeFromTheDirectoryOrTheDomain(): void
    {
        $command = new MigrateWithService(new RecordingMigrationService(), []);

        $this->assertSame('Shop', $command->resolveModuleName('@SHOP', $this->root . '/Shop'));
        $this->assertSame('ACME', $command->resolveModuleName('@ACME', ''));
        $this->assertSame('module', $command->resolveModuleName('@/', ''));
        $this->assertTrue($command->matchesFilter('@SHOP', 'Shop', ''));
        $this->assertTrue($command->matchesFilter('@SHOP', 'Shop', 'sh'));
        $this->assertFalse($command->matchesFilter('@SHOP', 'Shop', 'back'));
        $this->assertNull($command->parseTarget(null));
        $this->assertSame(0, $command->parseTarget('0'));
    }

    /** @return array<string, array{base:string}> */
    private function domains(): array
    {
        return [
            '@ROOT' => ['base' => ''],
            '@SHOP' => ['base' => $this->root . '/Shop'],
            '@BACKOFFICE' => ['base' => $this->root . '/Backoffice'],
            '@STATIC' => ['base' => $this->root . '/Static'],
        ];
    }
}

final class MigrateWithService extends MigrateDatabaseCommand
{
    /** @param array<string, array{base:string}> $domains */
    public function __construct(private readonly MigrationService $migrations, private readonly array $moduleDomains)
    {
        parent::__construct();
    }

    protected function domains(): array
    {
        return $this->moduleDomains;
    }

    protected function service(): MigrationService
    {
        return $this->migrations;
    }
}

final class RollbackWithService extends RollbackDatabaseMigrationCommand
{
    /** @param array<string, array{base:string}> $domains */
    public function __construct(private readonly MigrationService $migrations, private readonly array $moduleDomains)
    {
        parent::__construct();
    }

    protected function domains(): array
    {
        return $this->moduleDomains;
    }

    protected function service(): MigrationService
    {
        return $this->migrations;
    }
}

final class RecordingMigrationService extends MigrationService
{
    /** @var list<array{string, string, bool, ?string, ?int}> */
    public array $calls = [];

    public function __construct(private readonly bool $success = true)
    {
    }

    public function runMigrate(string $module, string $moduleBasePath, bool $simulate = false, ?string $engineName = null, ?int $targetVersion = null): MigrationExecutionResult
    {
        return $this->record('migrate', $module, $simulate, $engineName, $targetVersion);
    }

    public function runRollback(string $module, string $moduleBasePath, bool $simulate = false, ?string $engineName = null, ?int $targetVersion = null): MigrationExecutionResult
    {
        return $this->record('rollback', $module, $simulate, $engineName, $targetVersion);
    }

    private function record(string $action, string $module, bool $simulate, ?string $engine, ?int $target): MigrationExecutionResult
    {
        $this->calls[] = [$action, $module, $simulate, $engine, $target];
        return $this->success
            ? MigrationExecutionResult::success('phinx', $action . ' ' . $module)
            : MigrationExecutionResult::failure('phinx', $action . ' failed');
    }
}
