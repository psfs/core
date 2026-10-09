<?php

namespace PSFS\services\migration;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\ColumnDefaultValue;
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
        $statements = [];

        foreach ($this->sortNamed($diff->getRenamedTables()) as $oldName => $newName) {
            $statements[] = sprintf(
                '$this->table(%s)->rename(%s)->update();',
                $this->export($oldName),
                $this->export($newName)
            );
        }

        foreach ($this->sortNamed($diff->getModifiedTables()) as $name => $tableDiff) {
            if (!$tableDiff instanceof TableDiff) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported Propel table diff node for "%s": %s',
                    $name,
                    get_debug_type($tableDiff)
                ));
            }
            $table = '$this->table(' . $this->export($name) . ')';
            foreach ($this->sortNamed($tableDiff->getRemovedFks()) as $foreignKey) {
                $statements[] = $this->renderDropForeignKey($table, $foreignKey);
            }
            foreach ($this->sortNamed($tableDiff->getModifiedFks()) as [$from]) {
                $statements[] = $this->renderDropForeignKey($table, $from);
            }
        }
        foreach ($this->sortNamed($diff->getRemovedTables()) as $name => $removedTable) {
            foreach ($this->sortNamed($removedTable->getForeignKeys()) as $foreignKey) {
                $statements[] = '$this->table(' . $this->export($name) . ')->dropForeignKey('
                    . $this->export($foreignKey->getLocalColumns()) . ', '
                    . $this->export($foreignKey->getName()) . ')->update();';
            }
        }
        foreach ($this->sortNamed($diff->getRemovedTables()) as $name => $_removedTable) {
            $statements[] = sprintf('$this->table(%s)->drop()->update();', $this->export($name));
        }

        foreach ($this->sortNamed($diff->getAddedTables()) as $table) {
            $statements[] = $this->renderCreateTable($table);
        }
        foreach ($this->sortNamed($diff->getAddedTables()) as $name => $table) {
            foreach ($this->sortNamed($table->getForeignKeys()) as $foreignKey) {
                $statements[] = $this->renderForeignKeyOperation(
                    '$this->table(' . $this->export($name) . ')',
                    $foreignKey
                );
            }
        }

        foreach ($this->sortNamed($diff->getModifiedTables()) as $name => $tableDiff) {
            if (!$tableDiff instanceof TableDiff) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported Propel table diff node for "%s": %s',
                    $name,
                    get_debug_type($tableDiff)
                ));
            }
            array_push($statements, ...$this->renderTableDiff($name, $tableDiff, true));
        }

        return $statements;
    }

    /** @return list<string> */
    private function renderTableDiff(string $tableName, TableDiff $diff, bool $foreignKeyDropsHandled = false): array
    {
        $statements = [];
        $table = '$this->table(' . $this->export($tableName) . ')';

        if (!$foreignKeyDropsHandled) {
            foreach ($this->sortNamed($diff->getRemovedFks()) as $name => $foreignKey) {
                $columns = $foreignKey->getLocalColumns();
                $statements[] = $table . '->dropForeignKey(' . $this->export($columns) . ', '
                    . $this->export((string)$name) . ')->update();';
            }
        }
        foreach ($this->sortNamed($diff->getRemovedIndices()) as $name => $index) {
            $statements[] = $table . '->removeIndexByName(' . $this->export((string)$name) . ')->update();';
        }
        foreach ($this->sortNamed($diff->getModifiedIndices()) as $name => [$from, $to]) {
            $statements[] = $table . '->removeIndexByName(' . $this->export((string)$name) . ')->update();';
            $statements[] = $this->renderIndexOperation($table, $to, $to->isUnique());
        }

        $renamedColumns = $diff->getRenamedColumns();
        usort($renamedColumns, static fn(array $left, array $right): int => strcmp(
            $left[0]->getName(),
            $right[0]->getName()
        ));
        foreach ($renamedColumns as [$from, $to]) {
            $statements[] = $table . '->renameColumn(' . $this->export($from->getName()) . ', '
                . $this->export($to->getName()) . ')->update();';
        }
        foreach ($this->sortNamed($diff->getAddedColumns()) as $column) {
            $statements[] = $this->renderColumnOperation($table, 'addColumn', $column);
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
            $statements[] = $this->renderColumnOperation($table, 'changeColumn', $columnDiff->getToColumn());
        }
        if ($diff->hasModifiedPk()) {
            $toTable = $diff->getToTable();
            if (null === $toTable) {
                throw new \InvalidArgumentException(sprintf('Propel table diff for "%s" has no target table', $tableName));
            }
            $keys = array_map(static fn(Column $column): string => $column->getName(), $toTable->getPrimaryKey());
            $statements[] = $table . '->changePrimaryKey(' . $this->export($keys === [] ? null : $keys) . ')->update();';
        }

        // Update the key before removing old key columns. New and renamed key
        // columns are already in place, so both directions remain executable.
        foreach ($this->sortNamed($diff->getRemovedColumns()) as $name => $_column) {
            $statements[] = $table . '->removeColumn(' . $this->export((string)$name) . ')->update();';
        }
        foreach ($this->sortNamed($diff->getAddedIndices()) as $index) {
            $statements[] = $this->renderIndexOperation($table, $index, $index->isUnique());
        }

        foreach ($this->sortNamed($diff->getModifiedFks()) as [$from, $to]) {
            if (!$foreignKeyDropsHandled) {
                $statements[] = $this->renderDropForeignKey($table, $from);
            }
            $statements[] = $this->renderForeignKeyOperation($table, $to);
        }
        foreach ($this->sortNamed($diff->getAddedFks()) as $foreignKey) {
            $statements[] = $this->renderForeignKeyOperation($table, $foreignKey);
        }

        return $statements;
    }

    private function renderColumnOperation(string $table, string $method, Column $column): string
    {
        return $table . '->' . $method . '(' . $this->export($column->getName()) . ', '
            . $this->export($this->phinxType($column)) . ', '
            . $this->export($this->columnOptions($column)) . ')->update();';
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
        $primaryKey = array_map(
            static fn(Column $column): string => $column->getName(),
            $table->getPrimaryKey()
        );
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
                . ', ' . $this->export($this->phinxType($column))
                . ', ' . $this->export($this->columnOptions($column)) . ')';
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
        $columns = array_map(
            static fn(Column $column): string => $column->getName(),
            $index->getColumnObjects()
        );
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

        return '->addIndex(' . $this->export($columns) . ', ' . $this->export($options) . ')';
    }

    private function phinxType(Column $column): string
    {
        $type = strtoupper($column->getType());
        $mapping = [
            'BIT' => 'integer',
            'TINYINT' => 'integer',
            'SMALLINT' => 'integer',
            'INTEGER' => 'integer',
            'INT' => 'integer',
            'BIGINT' => 'biginteger',
            'BOOLEAN' => 'boolean',
            'BOOL' => 'boolean',
            'CHAR' => 'char',
            'VARCHAR' => 'string',
            'VARCHAR_IGNORECASE' => 'string',
            'LONGVARCHAR' => 'text',
            'CLOB' => 'text',
            'TINYTEXT' => 'text',
            'TEXT' => 'text',
            'BINARY' => 'binary',
            'VARBINARY' => 'binary',
            'BLOB' => 'blob',
            'TINYBLOB' => 'blob',
            'DECIMAL' => 'decimal',
            'NUMERIC' => 'decimal',
            'FLOAT' => 'float',
            'DOUBLE' => 'double',
            'REAL' => 'float',
            'DATE' => 'date',
            'TIME' => 'time',
            'DATETIME' => 'datetime',
            'TIMESTAMP' => 'timestamp',
            'UUID' => 'uuid',
            'JSON' => 'json',
            // Propel stores these logical types as numeric ordinals, not as SQL ENUM/SET.
            'ENUM' => 'tinyinteger',
            'SET' => 'integer',
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported Propel type "%s" for column "%s"',
                $type,
                $column->getFullyQualifiedName()
            ));
        }

        return $mapping[$type];
    }

    /** @return array<string, mixed> */
    private function columnOptions(Column $column): array
    {
        $type = $this->phinxType($column);
        $options = [];
        if ($column->hasDefaultValue()) {
            $options['default'] = $this->defaultValue($column->getDefaultValue());
        }
        if ($column->isAutoIncrement()) {
            $options['identity'] = true;
        }
        if (in_array($type, ['char', 'string', 'binary'], true) && null !== $column->getSize()) {
            $options['limit'] = $column->getSize();
        }
        if ('decimal' === $type && null !== $column->getSize()) {
            $options['precision'] = $column->getSize();
            $options['scale'] = $column->getScale();
        }
        $options['null'] = !$column->isNotNull();
        if ('' !== (string)$column->getDescription()) {
            $options['comment'] = $column->getDescription();
        }

        return $options;
    }

    /** @return mixed */
    private function defaultValue($value)
    {
        if ($value instanceof ColumnDefaultValue) {
            if ($value->isExpression()) {
                return new \Phinx\Util\Literal((string)$value->getValue());
            }

            $value = $value->getValue();
        }

        return $value;
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
}
