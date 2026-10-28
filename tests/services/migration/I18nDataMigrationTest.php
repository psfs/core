<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Table;
use PSFS\services\migration\I18nDataMigration;

class I18nDataMigrationTest extends TestCase
{
    public function testAColumnMovedIntoTheTranslationTableIsCopiedBeforeItIsDropped(): void
    {
        $main = $this->table('notes', [['ID', true], ['BODY', false]]);
        $mainWithout = $this->table('notes', [['ID', true]]);
        $translations = $this->translations('notes_i18n', 'es', ['BODY']);
        $tableDiff = new TableDiff($main, $mainWithout);
        $tableDiff->addRemovedColumn('BODY', $main->getColumn('BODY'));
        $diff = new DatabaseDiff();
        $diff->addAddedTable('notes_i18n', $translations);

        $statements = (new I18nDataMigration())->copyIntoTranslations($diff, ['notes' => $tableDiff]);

        $this->assertSame(
            ['$this->execute(\'INSERT INTO `notes_i18n` (`ID`, `locale`, `BODY`) SELECT `ID`, \\\'es\\\', `BODY` FROM `notes`\');'],
            $statements
        );
    }

    public function testAColumnThatComesBackFromTheTranslationTableIsCopiedBeforeTheTableIsDropped(): void
    {
        $without = $this->table('notes', [['ID', true]]);
        $main = $this->table('notes', [['ID', true], ['BODY', false]]);
        $translations = $this->translations('notes_i18n', "o'clock", ['BODY']);
        $tableDiff = new TableDiff($without, $main);
        $tableDiff->addAddedColumn('BODY', $main->getColumn('BODY'));
        $diff = new DatabaseDiff();
        $diff->addRemovedTable('notes_i18n', $translations);

        $statements = (new I18nDataMigration())->copyBackFromTranslations($diff, ['notes' => $tableDiff]);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('UPDATE `notes` t INNER JOIN `notes_i18n` i ON t.`ID` = i.`ID` AND i.`locale` = ', $statements[0]);
        $this->assertStringContainsString('SET t.`BODY` = i.`BODY`', $statements[0]);
        $this->assertStringContainsString("o\\'\\'clock", $statements[0], 'the locale is escaped as a SQL string');
    }

    public function testNothingIsCopiedWithoutATranslationTableOrADefaultLocale(): void
    {
        $main = $this->table('notes', [['ID', true], ['BODY', false]]);
        $tableDiff = new TableDiff($main, $this->table('notes', [['ID', true]]));
        $tableDiff->addRemovedColumn('BODY', $main->getColumn('BODY'));
        $noLocale = new Table('notes_i18n');
        $noLocale->addColumn(new Column('BODY', 'VARCHAR', 10));
        $withoutDefault = new DatabaseDiff();
        $withoutDefault->addAddedTable('notes_i18n', $noLocale);
        $unrelated = new DatabaseDiff();

        $migration = new I18nDataMigration();

        $this->assertSame([], $migration->copyIntoTranslations($unrelated, ['notes' => $tableDiff]));
        $this->assertSame([], $migration->copyIntoTranslations($withoutDefault, ['notes' => $tableDiff]));
        $this->assertSame([], $migration->copyBackFromTranslations($unrelated, ['notes' => $tableDiff]));
    }

    public function testColumnsTheTranslationTableDoesNotHaveAreNotCopied(): void
    {
        $main = $this->table('notes', [['ID', true], ['OTHER', false]]);
        $tableDiff = new TableDiff($main, $this->table('notes', [['ID', true]]));
        $tableDiff->addRemovedColumn('OTHER', $main->getColumn('OTHER'));
        $diff = new DatabaseDiff();
        $diff->addAddedTable('notes_i18n', $this->translations('notes_i18n', 'es', ['BODY']));

        $this->assertSame([], (new I18nDataMigration())->copyIntoTranslations($diff, ['notes' => $tableDiff]));
    }

    /**
     * @param list<array{0: string, 1: bool}> $columns name and whether it belongs to the primary key
     */
    private function table(string $name, array $columns): Table
    {
        $table = new Table($name);
        foreach ($columns as [$column, $primary]) {
            $definition = new Column($column, $primary ? 'INTEGER' : 'VARCHAR', $primary ? null : 100);
            $definition->setPrimaryKey($primary);
            $table->addColumn($definition);
        }

        return $table;
    }

    /**
     * @param string[] $columns
     */
    private function translations(string $name, string $locale, array $columns): Table
    {
        $table = new Table($name);
        $key = new Column('ID', 'INTEGER');
        $key->setPrimaryKey(true);
        $table->addColumn($key);
        $language = new Column('locale', 'VARCHAR', 5);
        $language->setPrimaryKey(true);
        $language->setDefaultValue(new ColumnDefaultValue($locale, ColumnDefaultValue::TYPE_VALUE));
        $table->addColumn($language);
        foreach ($columns as $column) {
            $table->addColumn(new Column($column, 'VARCHAR', 100));
        }

        return $table;
    }
}
