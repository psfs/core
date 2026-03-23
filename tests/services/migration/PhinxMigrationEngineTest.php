<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\CommandRunner;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\PhinxConfigFactory;
use PSFS\services\migration\PhinxMigrationEngine;
use PSFS\services\migration\SqlStatementSplitter;

class PhinxMigrationEngineTest extends TestCase
{
    public function testMigrateBuildsCommandAndUsesDryRunWhenSimulate(): void
    {
        $runner = new CapturingCommandRunner(['exit_code' => 0, 'output' => 'migrated']);
        $engine = new PhinxMigrationEngine(
            $runner,
            new StaticPhinxConfigFactory('psfs_test'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $context = new MigrationExecutionContext('Client', '/tmp/config', '/tmp/migrations', true);
        $result = $engine->migrate($context);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString(' migrate ', (string)$result->getCommand());
        $this->assertStringContainsString(' --dry-run', (string)$result->getCommand());
        $this->assertStringContainsString(" -e 'psfs_test'", (string)$result->getCommand());
        $this->assertNotNull($runner->lastCommand);

        preg_match("/ -c '([^']+)' /", (string)$result->getCommand(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->assertFileDoesNotExist($matches[1]);
    }

    public function testStatusReturnsFailureWhenRunnerFails(): void
    {
        $runner = new CapturingCommandRunner(['exit_code' => 7, 'output' => 'broken']);
        $engine = new PhinxMigrationEngine(
            $runner,
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $context = new MigrationExecutionContext('Client', '/tmp/config', '/tmp/migrations', false);
        $result = $engine->status($context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(7, $result->getExitCode());
        $this->assertSame('broken', $result->getOutput());
    }

    public function testGenerateFromDiffCreatesMigrationClassWithSplitStatements(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_engine_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);

        $engine = new PhinxMigrationEngine(
            new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']),
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $timestamp = 1700000000;
        $result = $engine->generateFromDiff(
            'client',
            ['main' => "CREATE TABLE demo(id INT); INSERT INTO demo VALUES (1, 'a;b');"],
            ['main' => 'DROP TABLE demo;'],
            $tmpDir,
            $timestamp
        );

        $this->assertTrue($result->isSuccess());
        $file = $tmpDir . DIRECTORY_SEPARATOR . date('YmdHis', $timestamp) . '_AutoClientSchemaDiff.php';
        $this->assertFileExists($file);

        $content = (string)file_get_contents($file);
        $this->assertStringContainsString('final class AutoClientSchemaDiff', $content);
        $this->assertStringContainsString("'CREATE TABLE demo(id INT)'", $content);
        $this->assertMatchesRegularExpression('/INSERT INTO demo VALUES \\(1, \\\'a;b\\\'\\)/', $content);
        $this->assertStringContainsString("'DROP TABLE demo'", $content);

        @unlink($file);
        @rmdir($tmpDir);
    }
}

final class StaticPhinxConfigFactory extends PhinxConfigFactory
{
    public function __construct(private string $defaultEnvironment)
    {
    }

    public function createForModule(string $module, string $migrationDir): array
    {
        return [
            'paths' => ['migrations' => $migrationDir],
            'environments' => [
                'default_environment' => $this->defaultEnvironment,
                'psfs' => [],
            ],
        ];
    }
}

final class CapturingCommandRunner extends CommandRunner
{
    public ?string $lastCommand = null;
    public ?string $lastCwd = null;

    /**
     * @param array{exit_code:int, output:string} $nextResult
     */
    public function __construct(private array $nextResult)
    {
    }

    public function run(string $command, ?string $cwd = null): array
    {
        $this->lastCommand = $command;
        $this->lastCwd = $cwd;
        return $this->nextResult;
    }
}
