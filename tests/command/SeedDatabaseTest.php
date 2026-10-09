<?php

namespace PSFS\tests\command;

use PHPUnit\Framework\TestCase;
use PSFS\services\MigrationService;
use PSFS\services\migration\MigrationExecutionResult;
use PSFS\command\SeedDatabaseCommand;
use Symfony\Component\Console\Tester\CommandTester;

class SeedDatabaseTest extends TestCase
{
    public function testRunsSeedsForAllConfiguredModulesByDefault(): void
    {
        [$domains, $directories] = $this->createModules(['Shop', 'Backoffice']);
        $service = new SeedCommandServiceSpy();
        $tester = new CommandTester(SeedDatabaseCommand::create($service, $domains));

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertSame(['Shop', 'Backoffice'], $service->modules);
        $this->assertStringContainsString('Running seeders for module Shop', $tester->getDisplay());
        $this->assertStringContainsString('Running seeders for module Backoffice', $tester->getDisplay());
        $this->assertStringContainsString('Seeding completed', $tester->getDisplay());
        $this->removeModules($directories);
    }

    public function testModuleOptionFiltersSeederExecution(): void
    {
        [$domains, $directories] = $this->createModules(['Shop', 'Backoffice']);
        $service = new SeedCommandServiceSpy();
        $tester = new CommandTester(SeedDatabaseCommand::create($service, $domains));

        $exitCode = $tester->execute(['--module' => 'back']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertSame(['Backoffice'], $service->modules);
        $this->assertStringContainsString('Running seeders for module Backoffice', $tester->getDisplay());
        $this->assertStringNotContainsString('Running seeders for module Shop', $tester->getDisplay());
        $this->removeModules($directories);
    }

    public function testSeederFailureProducesNonzeroExitAndUsefulOutput(): void
    {
        [$domains, $directories] = $this->createModules(['Shop']);
        $service = new SeedCommandServiceSpy(false);
        $tester = new CommandTester(SeedDatabaseCommand::create($service, $domains));

        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('database seeder failed', $tester->getDisplay());
        $this->assertStringContainsString('Seeding failed for: Shop', $tester->getDisplay());
        $this->removeModules($directories);
    }

    /** @return array{array<string, array{base:string}>, list<string>} */
    private function createModules(array $names): array
    {
        $domains = [];
        $directories = [];
        $root = CACHE_DIR . DIRECTORY_SEPARATOR . 'seed-command-' . uniqid('', true);
        foreach ($names as $name) {
            $base = $root . DIRECTORY_SEPARATOR . $name;
            mkdir($base . DIRECTORY_SEPARATOR . 'Config', 0777, true);
            $domains['@' . strtoupper($name)] = ['base' => $base];
            $directories[] = $base;
        }
        $directories[] = $root;
        $domains['@ROOT'] = ['base' => ''];

        return [$domains, $directories];
    }

    private function removeModules(array $directories): void
    {
        foreach ($directories as $directory) {
            @rmdir($directory . DIRECTORY_SEPARATOR . 'Config');
            @rmdir($directory);
        }
    }
}

final class SeedCommandServiceSpy extends MigrationService
{
    /** @var list<string> */
    public array $modules = [];

    public function __construct(private bool $success = true)
    {
    }

    public function runSeeds(string $module, string $moduleBasePath): MigrationExecutionResult
    {
        $this->modules[] = $module;
        return $this->success
            ? MigrationExecutionResult::success('phinx', 'seeded ' . $module)
            : MigrationExecutionResult::failure('phinx', 'database seeder failed', 9);
    }
}
