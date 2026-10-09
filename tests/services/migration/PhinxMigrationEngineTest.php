<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Table as PropelTable;
use PSFS\services\migration\CommandRunner;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\PhinxConfigFactory;
use PSFS\services\migration\PhinxMigrationEngine;
use PSFS\services\migration\SqlStatementSplitter;

class PhinxMigrationEngineTest extends TestCase
{
    public function testAvailabilitySupportsInjectedAndFilesystemChecks(): void
    {
        $runner = new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']);
        $checkedBinary = null;
        $engineWithChecker = new PhinxMigrationEngine(
            $runner,
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static function (string $binary) use (&$checkedBinary): bool {
                $checkedBinary = $binary;
                return true;
            }
        );
        $engineWithoutChecker = new PhinxMigrationEngine(
            $runner,
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter()
        );

        $this->assertTrue($engineWithChecker->isAvailable());
        $this->assertNotEmpty($checkedBinary);
        $this->assertIsBool($engineWithoutChecker->isAvailable());
    }

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
        $version = date('YmdHis', $timestamp);
        $file = $tmpDir . DIRECTORY_SEPARATOR . $version . '_AutoClientSchemaDiff' . $version . '.php';
        $this->assertFileExists($file);

        $content = (string)file_get_contents($file);
        $this->assertStringContainsString('final class AutoClientSchemaDiff' . $version, $content);
        $this->assertStringContainsString("'CREATE TABLE demo(id INT)'", $content);
        $this->assertMatchesRegularExpression('/INSERT INTO demo VALUES \\(1, \\\'a;b\\\'\\)/', $content);
        $this->assertStringContainsString("'DROP TABLE demo'", $content);

        @unlink($file);
        @rmdir($tmpDir);
    }

    public function testGeneratedMigrationClassNamesRemainUniqueWithinTheSameSecond(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_same_second_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $engine = new PhinxMigrationEngine(
            new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']),
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );
        $timestamp = 1700000000;

        foreach (['CREATE TABLE first_table(id INT)', 'CREATE TABLE second_table(id INT)'] as $statement) {
            $result = $engine->generateFromDiff('client', ['main' => [$statement]], ['main' => []], $tmpDir, $timestamp);
            $this->assertTrue($result->isSuccess());
        }

        $files = glob($tmpDir . DIRECTORY_SEPARATOR . '*_AutoClientSchemaDiff*.php') ?: [];
        $this->assertCount(2, $files);
        $this->assertNotSame(file_get_contents($files[0]), file_get_contents($files[1]));
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);
    }

    public function testMigrateAndRollbackForwardPhinxTargetVersion(): void
    {
        $runner = new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']);
        $engine = new PhinxMigrationEngine(
            $runner,
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );
        $context = new MigrationExecutionContext('Client', '/tmp/config', '/tmp/migrations', false, 20261009123456);

        $migrate = $engine->migrate($context);
        $this->assertTrue($migrate->isSuccess());
        $this->assertStringContainsString(' --target=20261009123456', (string)$migrate->getCommand());

        $rollback = $engine->rollback($context);
        $this->assertTrue($rollback->isSuccess());
        $this->assertStringContainsString(' --target=20261009123456', (string)$rollback->getCommand());
    }

    public function testGenerateFromPropelDiffWritesDeclarativeUpAndDownMethods(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_declarative_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $table = new PropelTable('users');
        $table->addColumn(new \Propel\Generator\Model\Column('id', 'INTEGER'));
        $diff = new DatabaseDiff();
        $diff->addAddedTable('users', $table);
        $reverse = $diff->getReverseDiff();

        $engine = new PhinxMigrationEngine(
            new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']),
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );
        $result = $engine->generateFromDiff('client', ['main' => $diff], ['main' => $reverse], $tmpDir, 1700000000);

        $this->assertTrue($result->isSuccess());
        $files = glob($tmpDir . DIRECTORY_SEPARATOR . '*_AutoClientSchemaDiff*.php');
        $this->assertCount(1, $files);
        $file = $files[0];
        $content = (string)file_get_contents($file);
        $this->assertStringContainsString("\$this->table('users'", $content);
        $this->assertStringContainsString("\$this->table('users')->drop()->update()", $content);
        $this->assertStringNotContainsString('$this->execute(', $content);
        token_get_all($content, TOKEN_PARSE);

        @unlink($file);
        @rmdir($tmpDir);
    }

    public function testGenerateFromMultiplePropelDiffsRejectsUnboundDatasourceMapping(): void
    {
        $tmpDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_multi_diff_' . uniqid('', true);
        mkdir($tmpDir, 0777, true);
        $first = new DatabaseDiff();
        $second = new DatabaseDiff();
        $engine = new PhinxMigrationEngine(
            new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']),
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple datasources');
        $engine->generateFromDiff('client', ['main' => $first, 'audit' => $second], ['main' => $first, 'audit' => $second], $tmpDir, 1700000000);
    }

    public function testGenerateFromMismatchedPropelDiffKeysIsRejected(): void
    {
        $diff = new DatabaseDiff();
        $engine = new PhinxMigrationEngine(
            new CapturingCommandRunner(['exit_code' => 0, 'output' => 'ok']),
            new StaticPhinxConfigFactory('psfs'),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('matching Propel DatabaseDiff objects');

        $engine->generateFromDiff('client', ['main' => $diff], ['audit' => $diff], sys_get_temp_dir(), 1700000000);
    }

    public function testSeedRunsPhinxSeedCommandAndPassesThroughOutput(): void
    {
        $migrationDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_seed_' . uniqid('', true);
        mkdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds', 0777, true);
        file_put_contents($migrationDir . DIRECTORY_SEPARATOR . 'Seeds' . DIRECTORY_SEPARATOR . 'UsersSeeder.php', '<?php');
        $runner = new CapturingCommandRunner(['exit_code' => 0, 'output' => 'seeded']);
        $engine = new PhinxMigrationEngine($runner, new StaticPhinxConfigFactory('psfs'), new SqlStatementSplitter(), static fn(string $binary): bool => true);

        $result = $engine->seed(new MigrationExecutionContext('Client', '/tmp/config', $migrationDir));

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString(' seed:run ', (string)$result->getCommand());
        $this->assertStringContainsString(" -e 'psfs'", (string)$result->getCommand());
        $this->assertSame('seeded', $result->getOutput());
        $this->assertSame($runner->lastCommand, $result->getCommand());
        @unlink($migrationDir . DIRECTORY_SEPARATOR . 'Seeds' . DIRECTORY_SEPARATOR . 'UsersSeeder.php');
        @rmdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds');
        @rmdir($migrationDir);
    }

    public function testSeedFailsWhenSeedDirectoryIsMissingOrEmpty(): void
    {
        $migrationDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_seed_empty_' . uniqid('', true);
        mkdir($migrationDir, 0777, true);
        $runner = new CapturingCommandRunner(['exit_code' => 0, 'output' => 'should not run']);
        $engine = new PhinxMigrationEngine($runner, new StaticPhinxConfigFactory('psfs'), new SqlStatementSplitter(), static fn(string $binary): bool => true);

        $result = $engine->seed(new MigrationExecutionContext('Client', '/tmp/config', $migrationDir));

        $this->assertFalse($result->isSuccess());
        $this->assertSame(1, $result->getExitCode());
        $this->assertStringContainsString('No Phinx seeders found', $result->getOutput());
        $this->assertNull($runner->lastCommand);
        mkdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds', 0777, true);
        $emptyResult = $engine->seed(new MigrationExecutionContext('Client', '/tmp/config', $migrationDir));
        $this->assertFalse($emptyResult->isSuccess());
        $this->assertStringContainsString('No Phinx seeders found', $emptyResult->getOutput());
        @rmdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds');
        @rmdir($migrationDir);
    }

    public function testSeedPassesThroughPhinxProcessFailure(): void
    {
        $migrationDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx_seed_failure_' . uniqid('', true);
        mkdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds', 0777, true);
        file_put_contents($migrationDir . DIRECTORY_SEPARATOR . 'Seeds' . DIRECTORY_SEPARATOR . 'BrokenSeeder.php', '<?php');
        $runner = new CapturingCommandRunner(['exit_code' => 9, 'output' => 'seeder failed']);
        $engine = new PhinxMigrationEngine($runner, new StaticPhinxConfigFactory('psfs'), new SqlStatementSplitter(), static fn(string $binary): bool => true);

        $result = $engine->seed(new MigrationExecutionContext('Client', '/tmp/config', $migrationDir));

        $this->assertFalse($result->isSuccess());
        $this->assertSame(9, $result->getExitCode());
        $this->assertSame('seeder failed', $result->getOutput());
        @unlink($migrationDir . DIRECTORY_SEPARATOR . 'Seeds' . DIRECTORY_SEPARATOR . 'BrokenSeeder.php');
        @rmdir($migrationDir . DIRECTORY_SEPARATOR . 'Seeds');
        @rmdir($migrationDir);
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
