<?php

namespace PSFS\services\migration;

use Propel\Generator\Model\Diff\DatabaseDiff;
use Propel\Generator\Model\Diff\TableDiff;
use Propel\Generator\Model\Table;

/**
 * The i18n behavior keeps the translated columns in a <table>_i18n table. When a schema change moves a column into
 * that table (the behavior is added) or back out of it (the behavior is removed), the data of the column goes with
 * it: the migration copies it, in the default locale of the translation table, instead of dropping it.
 */
final class I18nDataMigration
{
    private const SUFFIX = '_i18n';
    private const LOCALE = 'locale';

    /**
     * Statements that run after the translation tables exist and before the moved columns are dropped
     *
     * @param array<string, TableDiff> $modifiedTables
     * @return list<string>
     */
    public function copyIntoTranslations(DatabaseDiff $diff, array $modifiedTables): array
    {
        $statements = [];
        foreach ($modifiedTables as $name => $tableDiff) {
            $translations = $diff->getAddedTables()[$name . self::SUFFIX] ?? null;
            $fromTable = $tableDiff->getFromTable();
            if (null === $translations || null === $fromTable) {
                continue;
            }
            $moved = $this->movedColumns(array_keys($tableDiff->getRemovedColumns()), $translations);
            $locale = $this->defaultLocale($translations);
            if ([] !== $moved && null !== $locale) {
                $statements[] = $this->insertStatement($name, $translations, $this->keyColumns($fromTable), $moved, $locale);
            }
        }

        return $statements;
    }

    /**
     * Statements that run after the columns came back to the table and before its translation table is dropped
     *
     * @param array<string, TableDiff> $modifiedTables
     * @return list<string>
     */
    public function copyBackFromTranslations(DatabaseDiff $diff, array $modifiedTables): array
    {
        $statements = [];
        foreach ($modifiedTables as $name => $tableDiff) {
            $translations = $diff->getRemovedTables()[$name . self::SUFFIX] ?? null;
            $toTable = $tableDiff->getToTable();
            if (null === $translations || null === $toTable) {
                continue;
            }
            $moved = $this->movedColumns(array_keys($tableDiff->getAddedColumns()), $translations);
            $locale = $this->defaultLocale($translations);
            if ([] !== $moved && null !== $locale) {
                $statements[] = $this->updateStatement($name, $translations, $this->keyColumns($toTable), $moved, $locale);
            }
        }

        return $statements;
    }

    /**
     * @param string[] $columns
     * @return string[] the columns that also exist in the translation table
     */
    private function movedColumns(array $columns, Table $translations): array
    {
        return array_values(array_filter(
            array_map('strval', $columns),
            static fn(string $column): bool => $translations->hasColumn($column) && self::LOCALE !== $column
        ));
    }

    private function defaultLocale(Table $translations): ?string
    {
        if (!$translations->hasColumn(self::LOCALE) || !$translations->getColumn(self::LOCALE)->hasDefaultValue()) {
            return null;
        }

        return (string)$translations->getColumn(self::LOCALE)->getDefaultValue()->getValue();
    }

    /**
     * @return string[]
     */
    private function keyColumns(Table $table): array
    {
        return array_map(static fn($column): string => $column->getName(), $table->getPrimaryKey());
    }

    /**
     * @param string[] $keys
     * @param string[] $moved
     */
    private function insertStatement(string $table, Table $translations, array $keys, array $moved, string $locale): string
    {
        $columns = array_merge($keys, [self::LOCALE], $moved);
        $selected = array_merge($keys, [$this->literal($locale)], $moved);

        return $this->execute(sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $this->quote($translations->getName()),
            implode(', ', array_map($this->quote(...), $columns)),
            implode(', ', array_map($this->selectable(...), $selected)),
            $this->quote($table)
        ));
    }

    /**
     * @param string[] $keys
     * @param string[] $moved
     */
    private function updateStatement(string $table, Table $translations, array $keys, array $moved, string $locale): string
    {
        $joins = array_map(
            fn(string $key): string => sprintf('t.%s = i.%s', $this->quote($key), $this->quote($key)),
            $keys
        );
        $joins[] = sprintf('i.%s = %s', $this->quote(self::LOCALE), $this->literal($locale));
        $assignments = array_map(
            fn(string $column): string => sprintf('t.%s = i.%s', $this->quote($column), $this->quote($column)),
            $moved
        );

        return $this->execute(sprintf(
            'UPDATE %s t INNER JOIN %s i ON %s SET %s',
            $this->quote($table),
            $this->quote($translations->getName()),
            implode(' AND ', $joins),
            implode(', ', $assignments)
        ));
    }

    private function execute(string $sql): string
    {
        return '$this->execute(' . var_export($sql, true) . ');';
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function literal(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Column names are quoted, literals (already between quotes) are not
     */
    private function selectable(string $value): string
    {
        return str_starts_with($value, "'") ? $value : $this->quote($value);
    }
}
