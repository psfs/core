<?php

namespace PSFS\apitests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PSFS\apitests\support\SchemaEvolutionLab;

/**
 * Real schema changes, applied by the Phinx migrations the generator writes: the database must end up as the new
 * schema says, the change must be reversible, and regenerating must not find anything left to migrate.
 */
#[Group('api')]
#[Group('api-phase-d-schema-evolution')]
class ApiPhaseDSchemaEvolutionTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../tests/examples/generator/Evolution/';

    private SchemaEvolutionLab $lab;

    protected function setUp(): void
    {
        $this->lab = new SchemaEvolutionLab();
    }

    protected function tearDown(): void
    {
        $this->lab->stop();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function evolutions(): array
    {
        return [
            'columns' => ['columns.xml'],
            'indexes' => ['indexes.xml'],
            'foreign keys' => ['foreign-keys.xml'],
            'primary keys' => ['primary-keys.xml'],
            'tables and behaviors' => ['tables.xml'],
            'composite keys' => ['composite.xml'],
            'defaults and expressions' => ['defaults.xml'],
        ];
    }

    #[DataProvider('evolutions')]
    public function testTheDatabaseEndsUpAsTheNewSchemaSaysAndNothingIsLeftToMigrate(string $target): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $generated = $this->lab->useSchema(self::FIXTURES . $target);
        $this->assertCount(1, $generated, 'one migration is generated for the change');
        $this->lab->migrate();

        $this->assertSame([], $this->lab->useSchema(self::FIXTURES . $target), 'the database already matches the schema');
    }

    public function testTheFirstMigrationCreatesWhatPropelWouldCreate(): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $this->assertSame($this->lab->propelReference(), $this->lab->snapshot());
    }

    #[DataProvider('evolutions')]
    public function testEvolvingTheDatabaseGivesWhatPropelWouldCreateForTheNewSchema(string $target): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $this->lab->useSchema(self::FIXTURES . $target);
        $reference = $this->lab->propelReference();
        $this->lab->migrate();

        $this->assertSame($reference, $this->lab->snapshot());
    }

    #[DataProvider('evolutions')]
    public function testEveryChangeCanBeRolledBackToTheOriginalStructure(string $target): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();
        $original = $this->lab->snapshot();
        $initialVersion = $this->lab->versions()[0];

        $this->lab->useSchema(self::FIXTURES . $target);
        $this->lab->migrate();
        $this->assertNotSame($original, $this->lab->snapshot(), 'the change altered the database');

        $this->lab->rollback($initialVersion);

        $this->assertSame($original, $this->lab->snapshot());
    }

    public function testTablesThatSchemaNoLongerHasAreKeptUnlessRemovalIsEnabled(): void
    {
        $this->lab->start();
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $this->lab->useSchema(self::FIXTURES . 'tables.xml');
        $this->lab->migrate();

        $this->assertArrayHasKey('MIGLAB_CATEGORY', $this->lab->snapshot()['columns'], 'the dropped table is still there');
        $this->assertArrayHasKey('MIGLAB_PUBLISHER', $this->lab->snapshot()['columns']);
    }

    public function testRemovingTablesNeverTouchesTheTablesOfOtherModulesInTheSameDatabase(): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();
        $this->lab->execute('CREATE TABLE OTHER_MODULE_ORDERS (ID INT NOT NULL PRIMARY KEY, TOTAL DECIMAL(10,2))');
        $this->lab->execute('INSERT INTO OTHER_MODULE_ORDERS VALUES (1, 9.50)');

        $this->lab->useSchema(self::FIXTURES . 'tables.xml');
        $this->lab->migrate();

        $this->assertArrayNotHasKey('MIGLAB_CATEGORY', $this->lab->snapshot()['columns']);
        $this->assertSame('9.50', (string)$this->lab->scalar('SELECT TOTAL FROM OTHER_MODULE_ORDERS WHERE ID = 1'));
        $this->assertSame([], $this->lab->useSchema(self::FIXTURES . 'tables.xml'), 'the foreign table is not reported as a difference');
    }

    public function testTheDataSurvivesTheChangesAndColumnsMovedToTheTranslationTableKeepTheirValues(): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();
        $this->lab->execute("INSERT INTO MIGLAB_AUTHOR (NAME, SCORE) VALUES ('Ana', 4.50), ('Luis', 1.25)");
        $this->lab->execute("INSERT INTO MIGLAB_BOOK (ID_AUTHOR, ISBN, TITLE, STATUS) VALUES (1, '9780000000001', 'First', 1)");
        $this->lab->execute("INSERT INTO MIGLAB_TYPES (C_TEXT, C_TINY) VALUES ('hello', 3), ('world', 4)");
        $this->lab->execute("INSERT INTO MIGLAB_NOTE (ID) VALUES (1)");
        $this->lab->execute("INSERT INTO MIGLAB_NOTE_i18n (ID, locale, BODY) VALUES (1, 'es', 'nota')");
        $initial = $this->lab->versions()[0];

        $this->lab->useSchema(self::FIXTURES . 'tables.xml');
        $this->lab->migrate();

        $this->assertSame(2, (int)$this->lab->scalar('SELECT COUNT(*) FROM MIGLAB_AUTHOR'));
        $this->assertSame(1, (int)$this->lab->scalar('SELECT COUNT(*) FROM MIGLAB_BOOK'));
        $this->assertSame('hello', $this->lab->scalar("SELECT C_TEXT FROM MIGLAB_TYPES_i18n WHERE ID = 1 AND locale = 'es'"));
        $this->assertSame('world', $this->lab->scalar("SELECT C_TEXT FROM MIGLAB_TYPES_i18n WHERE ID = 2 AND locale = 'es'"));
        $this->assertSame('nota', $this->lab->scalar('SELECT BODY FROM MIGLAB_NOTE WHERE ID = 1'), 'the column that left the translation table keeps its value');

        $this->lab->rollback($initial);

        $this->assertSame('hello', $this->lab->scalar('SELECT C_TEXT FROM MIGLAB_TYPES WHERE ID = 1'), 'the rollback brings the moved column back with its value');
        $this->assertSame('nota', $this->lab->scalar("SELECT BODY FROM MIGLAB_NOTE_i18n WHERE ID = 1 AND locale = 'es'"));
    }

    public function testASuccessionOfDifferentChangesKeepsTheDatabaseEqualToTheSchemaAtEveryStep(): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();
        $original = $this->lab->snapshot();
        $initial = $this->lab->versions()[0];

        foreach (['columns.xml', 'indexes.xml', 'foreign-keys.xml', 'primary-keys.xml', 'composite.xml', 'defaults.xml', 'tables.xml', 'base.xml'] as $step) {
            $this->lab->useSchema(self::FIXTURES . $step);
            $reference = $this->lab->propelReference();
            $this->lab->migrate();
            $this->assertSame($reference, $this->lab->snapshot(), 'the database after ' . $step);
            $this->assertSame([], $this->lab->useSchema(self::FIXTURES . $step), 'nothing left to migrate after ' . $step);
        }

        $this->assertSame($original, $this->lab->snapshot(), 'back to the first schema');
        $this->lab->rollback($initial);
        $this->assertSame($original, $this->lab->snapshot(), 'every migration reverted');
    }

    public function testMigratingTwiceChangesNothing(): void
    {
        $this->lab->start();
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();
        $snapshot = $this->lab->snapshot();

        $this->lab->migrate();

        $this->assertSame($snapshot, $this->lab->snapshot());
        $this->assertCount(1, $this->lab->versions());
    }

    public function testRenamingATableKeepsTheOldOneUnlessRemovalIsEnabled(): void
    {
        $this->lab->start();
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $this->lab->useSchema(self::FIXTURES . 'renamed-table.xml');
        $this->lab->migrate();

        $tables = array_keys($this->lab->snapshot()['columns']);
        $this->assertContains('MIGLAB_TAG', $tables);
        $this->assertContains('MIGLAB_LABEL_TAG', $tables);
    }

    public function testAnAmbiguousTableRenameIsNotGuessed(): void
    {
        $this->lab->start(removeTables: true);
        $this->lab->useSchema(self::FIXTURES . 'base.xml');
        $this->lab->migrate();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ambiguous');

        $this->lab->useSchema(self::FIXTURES . 'renamed-table.xml');
    }
}
