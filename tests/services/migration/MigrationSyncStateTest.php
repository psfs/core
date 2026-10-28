<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\services\migration\MigrationSyncState;

class MigrationSyncStateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = CACHE_DIR . DIRECTORY_SEPARATOR . 'sync-state-' . uniqid('', true);
        GeneratorHelper::createDir($this->root . '/Config');
        GeneratorHelper::createDir($this->root . '/Migrations');
        file_put_contents($this->root . '/Config/schema.xml', '<database name="A"/>');
        file_put_contents($this->root . '/Migrations/20260101000000_one.php', '');
    }

    protected function tearDown(): void
    {
        putenv('PSFS_GENERATOR_MIGRATION_SKIP_UNCHANGED');
        GeneratorHelper::deleteDir($this->root);
    }

    public function testItIsDisabledUnlessTheSwitchIsOn(): void
    {
        $this->assertFalse(MigrationSyncState::enabled());

        putenv('PSFS_GENERATOR_MIGRATION_SKIP_UNCHANGED=true');

        $this->assertTrue(MigrationSyncState::enabled());
    }

    public function testNothingMatchesUntilTheSignatureIsRemembered(): void
    {
        $state = new MigrationSyncState('Shop', $this->root);
        $signature = $this->signature();

        $this->assertFalse($state->matches($signature));

        $state->remember($signature);

        $this->assertTrue($state->matches($signature));
        $this->assertFileExists($state->file());
    }

    public function testTheSignatureChangesWithTheSchemaAndWithTheMigrations(): void
    {
        $original = $this->signature();

        file_put_contents($this->root . '/Config/schema.xml', '<database name="B"/>');
        $changedSchema = $this->signature();
        file_put_contents($this->root . '/Migrations/20260102000000_two.php', '');
        $changedMigrations = $this->signature();

        $this->assertNotSame($original, $changedSchema);
        $this->assertNotSame($changedSchema, $changedMigrations);
        $this->assertSame($changedMigrations, $this->signature());
    }

    public function testAChangedSignatureNoLongerMatchesAndForgettingClearsTheState(): void
    {
        $state = new MigrationSyncState('Shop', $this->root);
        $state->remember($this->signature());
        file_put_contents($this->root . '/Config/schema.xml', '<database name="C"/>');

        $this->assertFalse($state->matches($this->signature()));

        $state->remember($this->signature());
        $state->forget();

        $this->assertFalse($state->matches($this->signature()));
        $this->assertFileDoesNotExist($state->file());
    }

    public function testEachModuleHasItsOwnStateFile(): void
    {
        $shop = new MigrationSyncState('Shop', $this->root);
        $other = new MigrationSyncState('Back Office', $this->root);

        $this->assertNotSame($shop->file(), $other->file());
        $this->assertStringContainsString('generator-sync-back-office.json', $other->file());
    }

    private function signature(): string
    {
        return MigrationSyncState::signature($this->root . '/Config', $this->root . '/Migrations');
    }
}
