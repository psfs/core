<?php

namespace PSFS\base\types\traits\Api;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Request;
use PSFS\base\types\Api;

trait MutationExtraColumnsTrait
{
    /**
     * @throws ApiException
     */
    protected function addPkToList()
    {
        foreach ($this->getPkDbName() as $extraColumn => $columnName) {
            $this->extraColumns[$extraColumn] = $columnName;
        }
    }

    /**
     * @param TableMap $tableMap
     */
    private function addClassListName(TableMap $tableMap)
    {
        $segments = [];
        foreach ($tableMap->getPrimaryKeys() as $pk) {
            $segments[] = $pk->getFullyQualifiedName();
        }

        $this->extraColumns[$this->buildClassListNameExpression((string)$tableMap->getPhpName(), $segments)] = Api::API_LIST_NAME_FIELD;
    }

    /**
     * @param array<int, string> $segments
     */
    private function buildClassListNameExpression(string $phpName, array $segments): string
    {
        if (count($segments) === 0) {
            return 'CONCAT("' . $phpName . ' #")';
        }

        return 'CONCAT("' . $phpName . ' #", ' . implode(', "|", ', $segments) . ')';
    }

    protected function addDefaultListField()
    {
        if (!in_array(Api::API_LIST_NAME_FIELD, array_values($this->extraColumns), true)) {
            $tableMap = $this->getTableMap();
            $column = $this->resolveDefaultListColumn($tableMap);
            if (null !== $column) {
                $this->extraColumns[$column->getFullyQualifiedName()] = Api::API_LIST_NAME_FIELD;
            } else {
                $this->addClassListName($tableMap);
            }
        }
    }

    /**
     * @param ModelCriteria $query
     * @param mixed $action
     * @throws ApiException
     */
    private function addExtraColumns(ModelCriteria &$query, $action)
    {
        $this->prepareLegacyListExtraColumns((string)$action);
        if (empty($this->extraColumns)) {
            return;
        }

        $fields = $this->resolveRequestedExtraFields();
        foreach ($this->extraColumns as $expression => $columnName) {
            if ($this->shouldIncludeExtraColumn($columnName, $fields)) {
                $query->withColumn($expression, $columnName);
            }
        }
    }

    private function prepareLegacyListExtraColumns(string $action): void
    {
        if (Api::API_ACTION_LIST !== $action) {
            return;
        }
        // Legacy tokens kept for compatibility (`__name__`, `__pk`).
        // Planned future cleanup can remove them behind a versioned contract switch.
        $this->addDefaultListField();
        $this->addPkToList();
    }

    /**
     * @param array<int, string> $fields
     */
    private function shouldIncludeExtraColumn(string $columnName, array $fields): bool
    {
        return empty($fields) || in_array($columnName, $fields, true);
    }

    /**
     * @return array<string, string>
     */
    protected function parseExtraColumns()
    {
        $columns = [];
        foreach ($this->extraColumns as $columnName) {
            $columns[$columnName] = strtolower($columnName);
        }
        return $columns;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveRequestedExtraFields(): array
    {
        if (Config::getParam('api.extrafields.compat', true)) {
            return array_values($this->extraColumns);
        }
        return $this->normalizeRequestedExtraFields(
            (string)(Request::getInstance()->getQuery(Api::API_FIELDS_RESULT_FIELD) ?? '')
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRequestedExtraFields(string $returnFields): array
    {
        $fields = explode(',', $returnFields);
        $fields[] = self::API_MODEL_KEY_FIELD;
        $fields = array_values(array_filter(array_map('trim', $fields), static fn(string $field): bool => $field !== ''));

        return array_values(array_unique($fields));
    }

    private function resolveDefaultListColumn(TableMap $tableMap): ?ColumnMap
    {
        foreach (['NAME', 'TITLE', 'LABEL'] as $columnName) {
            if ($tableMap->hasColumn($columnName)) {
                return $tableMap->getColumn($columnName);
            }
        }
        return null;
    }
}
