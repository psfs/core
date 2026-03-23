<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Manager\MigrationManager;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\PropelMigrationEngine;

class PropelMigrationEngineTest extends TestCase
{
    public function testMigrateBuildsLegacyPropelCommandWithFakeFlag(): void
    {
        $runner = new PropelCapturingRunner(['exit_code' => 0, 'output' => 'ok']);
        $engine = new PropelMigrationEngine($runner);

        $context = new MigrationExecutionContext('Client', '/tmp/cfg', '/tmp/migrations', true);
        $result = $engine->migrate($context);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString(" migrate --fake --platform mysql --config-dir='/tmp/cfg' --output-dir='/tmp/migrations'", (string)$result->getCommand());
    }

    public function testRollbackBuildsLegacyCommand(): void
    {
        $runner = new PropelCapturingRunner(['exit_code' => 0, 'output' => 'ok']);
        $engine = new PropelMigrationEngine($runner);

        $result = $engine->rollback(new MigrationExecutionContext('Client', '/tmp/cfg', '/tmp/migrations', false));

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString(" migration:down --config-dir='/tmp/cfg' --output-dir='/tmp/migrations'", (string)$result->getCommand());
    }

    public function testGenerateFromDiffReturnsFailureWithoutManager(): void
    {
        $engine = new PropelMigrationEngine(new PropelCapturingRunner(['exit_code' => 0, 'output' => 'ok']));

        $result = $engine->generateFromDiff('client', ['up'], ['down'], '/tmp', 1, null);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('MigrationManager is required', $result->getOutput());
    }

    public function testGenerateFromDiffWritesPropelMigrationWhenManagerIsProvided(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'propel_engine_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $manager = $this->createMock(MigrationManager::class);
        $manager->expects($this->once())->method('getMigrationFileName')->with(123)->willReturn('Version123.php');
        $manager->expects($this->once())->method('getMigrationClassBody')->with(['up'], ['down'], 123)->willReturn('<?php // migration');

        $engine = new PropelMigrationEngine(new PropelCapturingRunner(['exit_code' => 0, 'output' => 'ok']));
        $result = $engine->generateFromDiff('client', ['up'], ['down'], $tmpDir, 123, $manager);

        $this->assertTrue($result->isSuccess());
        $file = $tmpDir . DIRECTORY_SEPARATOR . 'Version123.php';
        $this->assertFileExists($file);
        $this->assertSame('<?php // migration', (string)file_get_contents($file));

        @unlink($file);
        @rmdir($tmpDir);
    }
}

final class PropelCapturingRunner extends \PSFS\services\migration\CommandRunner
{
    /**
     * @param array{exit_code:int, output:string} $nextResult
     */
    public function __construct(private array $nextResult)
    {
    }

    public function run(string $command, ?string $cwd = null): array
    {
        return $this->nextResult;
    }
}
