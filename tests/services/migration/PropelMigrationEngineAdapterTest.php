<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Manager\MigrationManager;
use PSFS\services\migration\CommandRunner;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\PropelMigrationEngine;

class PropelMigrationEngineAdapterTest extends TestCase
{
    public function testMigrateAndRollbackBuildExpectedCommandFlags(): void
    {
        $runner = new FakeRunner();
        $engine = new PropelMigrationEngine($runner);
        $context = new MigrationExecutionContext('CLIENT', '/tmp/client/Config', '/tmp/client/Config/Migrations', true);

        $migrate = $engine->migrate($context);
        $rollback = $engine->rollback($context);

        $this->assertTrue($migrate->isSuccess());
        $this->assertTrue($rollback->isSuccess());
        $this->assertStringContainsString('propel', $runner->commands[0]);
        $this->assertStringContainsString('migrate', $runner->commands[0]);
        $this->assertStringContainsString('--fake', $runner->commands[0]);
        $this->assertStringContainsString('migration:down', $runner->commands[1]);
    }

    public function testGenerateFromDiffDelegatesToMigrationManagerFormat(): void
    {
        $runner = new FakeRunner();
        $engine = new PropelMigrationEngine($runner);

        $manager = $this->createMock(MigrationManager::class);
        $manager->method('getMigrationFileName')->with(1234)->willReturn('Version1234.php');
        $manager->method('getMigrationClassBody')->willReturn('<?php echo "legacy";');

        $dir = CACHE_DIR . DIRECTORY_SEPARATOR . 'propel-gen-' . uniqid('', true);
        mkdir($dir, 0777, true);

        $result = $engine->generateFromDiff('CLIENT', ['x' => 'up'], ['x' => 'down'], $dir, 1234, $manager);

        $this->assertTrue($result->isSuccess());
        $target = $dir . DIRECTORY_SEPARATOR . 'Version1234.php';
        $this->assertFileExists($target);
        $this->assertStringContainsString('legacy', (string)file_get_contents($target));

        @unlink($target);
        @rmdir($dir);
    }
}

class FakeRunner extends CommandRunner
{
    /** @var array<int, string> */
    public array $commands = [];

    public function run(string $command, ?string $cwd = null): array
    {
        $this->commands[] = $command;
        return ['exit_code' => 0, 'output' => 'ok'];
    }
}
