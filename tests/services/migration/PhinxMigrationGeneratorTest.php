<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\CommandRunner;
use PSFS\services\migration\PhinxConfigFactory;
use PSFS\services\migration\PhinxMigrationEngine;
use PSFS\services\migration\SqlStatementSplitter;

class PhinxMigrationGeneratorTest extends TestCase
{
    public function testGenerateFromDiffCreatesPhinxMigrationWithUpAndDownStatements(): void
    {
        $dir = CACHE_DIR . DIRECTORY_SEPARATOR . 'phinx-gen-' . uniqid('', true);
        mkdir($dir, 0777, true);

        $engine = new PhinxMigrationEngine(
            new CommandRunner(),
            new PhinxConfigFactory(),
            new SqlStatementSplitter(),
            static fn(string $binary): bool => true
        );

        $result = $engine->generateFromDiff(
            'CLIENT',
            ['CLIENT' => "ALTER TABLE test ADD COLUMN foo INT;\nALTER TABLE test ADD COLUMN bar INT;"],
            ['CLIENT' => "ALTER TABLE test DROP COLUMN bar;"],
            $dir,
            1700000000
        );

        $this->assertTrue($result->isSuccess());

        $files = glob($dir . DIRECTORY_SEPARATOR . '*_AutoCLIENTSchemaDiff.php');
        $this->assertNotEmpty($files);

        $content = (string)file_get_contents($files[0]);
        $this->assertStringContainsString('extends AbstractMigration', $content);
        $this->assertStringContainsString('ALTER TABLE test ADD COLUMN foo INT', $content);
        $this->assertStringContainsString('ALTER TABLE test DROP COLUMN bar', $content);

        @unlink($files[0]);
        @rmdir($dir);
    }
}
