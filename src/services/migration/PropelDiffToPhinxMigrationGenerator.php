<?php

namespace PSFS\services\migration;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Index;
use Propel\Generator\Model\Table;

/**
 * Converts Propel DatabaseDiff, TableDiff, and ColumnDiff nodes into declarative Phinx operations.
 *
 * The returned strings are PHP statements for Phinx's Table API, never SQL.
 */
final class PropelDiffToPhinxMigrationGenerator
{
    private readonly PropelColumnTranslator $columns;
    private readonly I18nDataMigration $i18n;

    public function __construct(?PropelColumnTranslator $columns = null, ?I18nDataMigration $i18n = null)
    {
        $this->columns = $columns ?? new PropelColumnTranslator();
        $this->i18n = $i18n ?? new I18nDataMigration();
    }

    /**
     * @return array{up: list<string>, down: list<string>}
     */
    public function translate(object $databaseDiff): array
    {
        if (!$databaseDiff instanceof DatabaseDiff) {
            throw new \InvalidArgumentException(sprintf(
                'Expected %s, got %s',
                DatabaseDiff::class,
                get_debug_type($databaseDiff)
            ));
        }

        if ($databaseDiff->getPossibleRenamedTables() !== []) {
            $table = (string)array_key_first($databaseDiff->getPossibleRenamedTables());
            throw new \InvalidArgumentException(sprintf(
                'Propel marked table rename "%s" as ambiguous; resolve the rename before generating a migration',
                $table
            ));
        }

        return [
            'up' => $this->renderDiff($databaseDiff),
            'down' => $this->renderDiff($databaseDiff->getReverseDiff()),
        ];
    }

    /** @return list<string> */
    private function renderDiff(DatabaseDiff $diff): array
    {
        $modifiedTables = $this->modifiedTables($diff);

        return array_merge(
            $this->renderRenamedTables($diff),
            $this->renderModifiedTableForeignKeyDrops($modifiedTables),
            $this->renderRemovedTableForeignKeyDrops($diff),
            $this->renderAddedTables($diff),
            $this->i18n->copyIntoTranslations($diff, $modifiedTables),
            $this->renderModifiedTables($modifiedTables),
            // foreign keys go last: the keys they reference may have been changed by the tables above
            $this->renderModifiedTableForeignKeys($modifiedTables),
            $this->renderAddedTableForeignKeys($diff),
            $this->i18n->copyBackFromTranslations($diff, $modifiedTables),
            $this->renderRemovedTables($diff)
        );
    }

    /** @return list<string> */
    private function renderTableDiff(string $tableName, TableDiff $diff): array
    {
        $table = '$this->table(' . $this->export($tableName) . ')';

        return $diff->hasModifiedPk()
            ? $this->renderTableDiffWithPrimaryKeyChange($tableName, $table, $diff)
            : array_merge(
                $this->renderRemovedIndices($table, $diff),
                $this->renderModifiedIndexDrops($table, $diff),
                $this->renderRenamedColumns($table, $diff),
                $this->renderAddedAndModifiedColumns($tableName, $table, $diff),
                $this->renderRemovedColumns($table, $diff),
                $this->renderModifiedIndexAdds($table, $diff),
                $this->renderAddedIndices($table, $diff)
            );
    }

    /**
     * Changing the primary key is a delicate step: a foreign key may rely on the old key as its index, and
     * MySQL only accepts an AUTO_INCREMENT column that is already a key. So the indexes the new key does not
     * replace are created before it changes, the ones it makes redundant are dropped after, and the columns that
     * become AUTO_INCREMENT are changed once the new key exists.
     *
     * @return list<string>
     */
    private function renderTableDiffWithPrimaryKeyChange(string $tableName, string $table, TableDiff $diff): array
    {
        return array_merge(
            $this->renderModifiedIndexDrops($table, $diff),
            $this->renderRenamedColumns($table, $diff),
            $this->renderAddedAndModifiedColumns($tableName, $table, $diff),
            $this->renderModifiedIndexAdds($table, $diff),
            $this->renderAddedIndices($table, $diff),
            $this->renderAutoIncrementRelease($table, $diff),
            $this->renderPrimaryKeyChange($tableName, $table, $diff),
            $this->renderColumnsAfterPrimaryKey($table, $diff),
            $this->renderRemovedIndices($table, $diff),
            $this->renderRemovedColumns($table, $diff)
        );
    }

    /** @return array<string, TableDiff> */
    private function modifiedTables(DatabaseDiff $diff): array
    {
        $tables = [];
        foreach ($this->sortNamed($diff->getModifiedTables()) as $name => $tableDiff) {
            if (!$tableDiff instanceof TableDiff) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported Propel table diff node for "%s": %s',
                    $name,
                    get_debug_type($tableDiff)
                ));
            }
            $tables[$name] = $tableDiff;
        }

        return $tables;
    }

    /** @return list<string> */
    private function renderRenamedTables(DatabaseDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRenamedTables()) as $oldName => $newName) {
            $statements[] = sprintf(
                '$this->table(%s)->rename(%s)->update();',
                $this->export($oldName),
                $this->export($newName)
            );
        }
        return $statements;
    }

    /** @param array<string, TableDiff> $tables @return list<string> */
    private function renderModifiedTableForeignKeyDrops(array $tables): array
    {
        $statements = [];
        foreach ($tables as $name => $diff) {
            $table = '$this->table(' . $this->export($name) . ')';
            foreach ($this->sortNamed($diff->getRemovedFks()) as $foreignKey) {
                $statements[] = $this->renderDropForeignKey($table, $foreignKey);
            }
            foreach ($this->sortNamed($diff->getModifiedFks()) as [$from]) {
                $statements[] = $this->renderDropForeignKey($table, $from);
            }
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderRemovedTableForeignKeyDrops(DatabaseDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRemovedTables()) as $name => $removedTable) {
            foreach ($this->sortNamed($removedTable->getForeignKeys()) as $foreignKey) {
                $statements[] = '$this->table(' . $this->export($name) . ')->dropForeignKey('
                    . $this->export($foreignKey->getLocalColumns()) . ', '
                    . $this->export($foreignKey->getName()) . ')->update();';
            }
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderRemovedTables(DatabaseDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRemovedTables()) as $name => $_removedTable) {
            $statements[] = sprintf('$this->table(%s)->drop()->update();', $this->export($name));
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderAddedTables(DatabaseDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getAddedTables()) as $table) {
            $statements[] = $this->renderCreateTable($table);
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderAddedTableForeignKeys(DatabaseDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getAddedTables()) as $name => $table) {
            foreach ($this->sortNamed($table->getForeignKeys()) as $foreignKey) {
                $statements[] = $this->renderForeignKeyOperation(
                    '$this->table(' . $this->export($name) . ')',
                    $foreignKey
                );
            }
        }
        return $statements;
    }

    /** @param array<string, TableDiff> $tables @return list<string> */
    private function renderModifiedTableForeignKeys(array $tables): array
    {
        $statements = [];
        foreach ($tables as $name => $tableDiff) {
            array_push($statements, ...$this->renderForeignKeyChanges('$this->table(' . $this->export($name) . ')', $tableDiff));
        }
        return $statements;
    }

    /** @param array<string, TableDiff> $tables @return list<string> */
    private function renderModifiedTables(array $tables): array
    {
        $statements = [];
        foreach ($tables as $name => $tableDiff) {
            array_push($statements, ...$this->renderTableDiff($name, $tableDiff));
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderRemovedIndices(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRemovedIndices()) as $name => $_index) {
            $statements[] = $table . '->removeIndexByName(' . $this->export((string)$name) . ')->update();';
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderModifiedIndexDrops(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getModifiedIndices()) as $name => $_indexes) {
            $statements[] = $table . '->removeIndexByName(' . $this->export((string)$name) . ')->update();';
        }
        return $statements;
    }

    /**
     * The new version of a modified index is created after the columns it may use exist
     *
     * @return list<string>
     */
    private function renderModifiedIndexAdds(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getModifiedIndices()) as [, $to]) {
            $statements[] = $this->renderIndexOperation($table, $to, $to->isUnique());
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderRenamedColumns(string $table, TableDiff $diff): array
    {
        $renamedColumns = $diff->getRenamedColumns();
        usort($renamedColumns, self::compareRenamedColumns(...));

        $statements = [];
        foreach ($renamedColumns as [$from, $to]) {
            $statements[] = $table . '->renameColumn(' . $this->export($from->getName()) . ', '
                . $this->export($to->getName()) . ')->update();';
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderAddedAndModifiedColumns(string $tableName, string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getAddedColumns()) as $column) {
            $statements[] = $this->renderColumnOperation($table, 'addColumn', $column, $this->waitsForPrimaryKey($diff, $column));
        }
        foreach ($this->sortNamed($diff->getModifiedColumns()) as $name => $columnDiff) {
            if (!$columnDiff instanceof \Propel\Generator\Model\Diff\ColumnDiff
                || null === $columnDiff->getToColumn()) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported Propel column diff for "%s.%s"',
                    $tableName,
                    $name
                ));
            }
            $column = $columnDiff->getToColumn();
            if (!$this->leavesPrimaryKey($diff, $column)) {
                $statements[] = $this->renderColumnOperation($table, 'changeColumn', $column, $this->waitsForPrimaryKey($diff, $column));
            }
        }
        return $statements;
    }

    /**
     * A column that stops being part of the primary key may become nullable only once the key no longer has it
     */
    private function leavesPrimaryKey(TableDiff $diff, Column $column): bool
    {
        if (!$diff->hasModifiedPk() || null === $diff->getFromTable() || null === $diff->getToTable()) {
            return false;
        }
        $before = array_map(self::columnName(...), $diff->getFromTable()->getPrimaryKey());
        $after = array_map(self::columnName(...), $diff->getToTable()->getPrimaryKey());

        return in_array($column->getName(), $before, true) && !in_array($column->getName(), $after, true);
    }

    /**
     * MySQL only accepts an AUTO_INCREMENT column that is already a key: when the primary key changes in the same
     * migration, the column is created or changed first and becomes AUTO_INCREMENT after the new key exists
     */
    private function waitsForPrimaryKey(TableDiff $diff, Column $column): bool
    {
        return $column->isAutoIncrement() && $diff->hasModifiedPk();
    }

    /**
     * An AUTO_INCREMENT column that is about to leave the table can not stay without a key: it stops being
     * AUTO_INCREMENT before the primary key changes
     *
     * @return list<string>
     */
    private function renderAutoIncrementRelease(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRemovedColumns()) as $column) {
            if ($column->isAutoIncrement()) {
                $statements[] = $this->renderColumnOperation($table, 'changeColumn', $column, true);
            }
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderColumnsAfterPrimaryKey(string $table, TableDiff $diff): array
    {
        $statements = [];
        $columns = array_filter($diff->getAddedColumns(), fn(Column $column): bool => $this->waitsForPrimaryKey($diff, $column));
        foreach ($diff->getModifiedColumns() as $columnDiff) {
            $column = $columnDiff instanceof \Propel\Generator\Model\Diff\ColumnDiff ? $columnDiff->getToColumn() : null;
            if (null !== $column && ($this->waitsForPrimaryKey($diff, $column) || $this->leavesPrimaryKey($diff, $column))) {
                $columns[$column->getName()] = $column;
            }
        }
        foreach ($this->sortNamed($columns) as $column) {
            $statements[] = $this->renderColumnOperation($table, 'changeColumn', $column);
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderPrimaryKeyChange(string $tableName, string $table, TableDiff $diff): array
    {
        if (!$diff->hasModifiedPk()) {
            return [];
        }

        $toTable = $diff->getToTable();
        if (null === $toTable) {
            throw new \InvalidArgumentException(sprintf('Propel table diff for "%s" has no target table', $tableName));
        }
        $keys = array_map(self::columnName(...), $toTable->getPrimaryKey());
        return [$table . '->changePrimaryKey(' . $this->export($keys === [] ? null : $keys) . ')->update();'];
    }

    /** @return list<string> */
    private function renderRemovedColumns(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getRemovedColumns()) as $name => $_column) {
            $statements[] = $table . '->removeColumn(' . $this->export((string)$name) . ')->update();';
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderAddedIndices(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getAddedIndices()) as $index) {
            $statements[] = $this->renderIndexOperation($table, $index, $index->isUnique());
        }
        return $statements;
    }

    /** @return list<string> */
    private function renderForeignKeyChanges(string $table, TableDiff $diff): array
    {
        $statements = [];
        foreach ($this->sortNamed($diff->getModifiedFks()) as [, $to]) {
            $statements[] = $this->renderForeignKeyOperation($table, $to);
        }
        foreach ($this->sortNamed($diff->getAddedFks()) as $foreignKey) {
            $statements[] = $this->renderForeignKeyOperation($table, $foreignKey);
        }
        return $statements;
    }

    private function renderColumnOperation(string $table, string $method, Column $column, bool $withoutAutoIncrement = false): string
    {
        $options = $this->columns->options($column);
        if ($withoutAutoIncrement) {
            unset($options['identity']);
        }

        return $table . '->' . $method . '(' . $this->export($column->getName()) . ', '
            . $this->export($this->columns->type($column)) . ', '
            . $this->export($options) . ')->update();';
    }

    private function renderIndexOperation(string $table, Index $index, bool $unique): string
    {
        return $table . $this->renderIndex($index, $unique) . '->update();';
    }

    private function renderForeignKeyOperation(string $table, $foreignKey): string
    {
        $localColumns = $foreignKey->getLocalColumns();
        $foreignColumns = $foreignKey->getForeignColumns();
        $options = ['constraint' => $foreignKey->getName()];
        if ($foreignKey->hasOnDelete()) {
            $options['delete'] = $foreignKey->getOnDelete();
        }
        if ($foreignKey->hasOnUpdate()) {
            $options['update'] = $foreignKey->getOnUpdate();
        }

        return $table . '->addForeignKey(' . $this->export($localColumns) . ', '
            . $this->export($foreignKey->getForeignTableName()) . ', '
            . $this->export($foreignColumns) . ', ' . $this->export($options) . ')->update();';
    }

    private function renderDropForeignKey(string $table, $foreignKey): string
    {
        return $table . '->dropForeignKey(' . $this->export($foreignKey->getLocalColumns()) . ', '
            . $this->export($foreignKey->getName()) . ')->update();';
    }

    private function renderCreateTable(Table $table): string
    {
        $primaryKey = array_map(self::columnName(...), $table->getPrimaryKey());
        $options = ['id' => false];
        if ($primaryKey !== []) {
            $options['primary_key'] = $primaryKey;
        }
        if ($table->hasDescription()) {
            $options['comment'] = $table->getDescription();
        }

        $statement = sprintf(
            '$this->table(%s, %s)',
            $this->export($table->getName()),
            $this->export($options)
        );

        foreach ($table->getColumns() as $column) {
            $statement .= '->addColumn(' . $this->export($column->getName())
                . ', ' . $this->export($this->columns->type($column))
                . ', ' . $this->export($this->columns->options($column)) . ')';
        }

        foreach ($table->getIndices() as $index) {
            $statement .= $this->renderIndex($index, false);
        }
        foreach ($table->getUnices() as $index) {
            $statement .= $this->renderIndex($index, true);
        }

        return $statement . '->create();';
    }

    private function renderIndex(Index $index, bool $unique): string
    {
        $columns = array_map(self::columnName(...), $index->getColumnObjects());
        if ($columns === []) {
            throw new \InvalidArgumentException(sprintf(
                'Propel index "%s" has no columns',
                (string)$index->getName()
            ));
        }

        $options = [];
        if (null !== $index->getName() && '' !== $index->getName()) {
            $options['name'] = $index->getName();
        }
        if ($unique) {
            $options['unique'] = true;
        }
        $limits = $this->prefixLengths($index, $columns);
        if ([] !== $limits) {
            $options['limit'] = $limits;
        }

        return '->addIndex(' . $this->export($columns) . ', ' . $this->export($options) . ')';
    }

    /**
     * Indexes over a text or binary column, or over only the first characters of a string, record the length they cover
     *
     * @param string[] $columns
     * @return array<string, int>
     */
    private function prefixLengths(Index $index, array $columns): array
    {
        $limits = [];
        foreach ($index->getColumnObjects() as $column) {
            $name = $column->getName();
            if (!in_array($name, $columns, true) || !$index->hasColumnSize($name)) {
                continue;
            }
            $length = (int)$index->getColumnSize($name);
            if ($this->isPrefix($column, $length)) {
                $limits[$name] = $length;
            }
        }

        return $limits;
    }

    private function isPrefix(Column $column, int $length): bool
    {
        return 'text' === $this->columns->type($column) || 'blob' === $this->columns->type($column)
            || null === $column->getSize() || $length < (int)$column->getSize();
    }

    /**
     * @param array<string, mixed> $value
     */
    private function export($value): string
    {
        if ($value instanceof \Phinx\Util\Literal) {
            return 'new \\Phinx\\Util\\Literal(' . var_export((string)$value, true) . ')';
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = (is_int($key) ? '' : var_export($key, true) . ' => ')
                    . $this->export($item);
            }
            return '[' . implode(', ', $items) . ']';
        }

        return var_export($value, true);
    }

    /** @return array<string, mixed> */
    private function sortNamed(array $items): array
    {
        ksort($items, SORT_STRING);
        return $items;
    }

    private static function columnName(Column $column): string
    {
        return $column->getName();
    }

    /**
     * @param array{0:Column,1:Column} $left
     * @param array{0:Column,1:Column} $right
     */
    private static function compareRenamedColumns(array $left, array $right): int
    {
        return strcmp($left[0]->getName(), $right[0]->getName());
    }
}
