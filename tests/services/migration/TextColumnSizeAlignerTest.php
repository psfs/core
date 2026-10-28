<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\Table;
use PSFS\services\migration\TextColumnSizeAligner;

class TextColumnSizeAlignerTest extends TestCase
{
    public function testTheSizeOfTheSchemaIsCopiedToTheTextColumnReadFromTheDatabase(): void
    {
        $current = $this->database([['summary', 'LONGVARCHAR', null], ['name', 'VARCHAR', 50]]);
        $wanted = $this->database([['summary', 'LONGVARCHAR', 2000], ['name', 'VARCHAR', 80]]);

        TextColumnSizeAligner::align($current, $wanted);

        $columns = $current->getTable('items')->getColumns();
        $this->assertSame(2000, $columns[0]->getSize());
        $this->assertSame(50, $columns[1]->getSize(), 'a size that really differs is still a change');
    }

    public function testTablesAndColumnsTheSchemaDoesNotHaveAreLeftAlone(): void
    {
        $current = $this->database([['legacy', 'LONGVARCHAR', null]]);
        $wanted = $this->database([['other', 'LONGVARCHAR', 100]]);
        $extra = new Table('extra');
        $extra->addColumn(new Column('notes', 'LONGVARCHAR'));
        $current->addTable($extra);

        TextColumnSizeAligner::align($current, $wanted);

        $this->assertNull($current->getTable('items')->getColumns()[0]->getSize());
        $this->assertNull($current->getTable('extra')->getColumns()[0]->getSize());
    }

    public function testASizeAlreadyKnownIsKept(): void
    {
        $current = $this->database([['summary', 'LONGVARCHAR', 500]]);
        $wanted = $this->database([['summary', 'LONGVARCHAR', 2000]]);

        TextColumnSizeAligner::align($current, $wanted);

        $this->assertSame(500, $current->getTable('items')->getColumns()[0]->getSize());
    }

    /**
     * @param list<array{0: string, 1: string, 2: ?int}> $columns
     */
    private function database(array $columns): Database
    {
        $table = new Table('items');
        foreach ($columns as [$name, $type, $size]) {
            $table->addColumn(new Column($name, $type, $size));
        }
        $database = new Database('app');
        $database->addTable($table);

        return $database;
    }
}
