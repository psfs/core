<?php

namespace PSFS\base\types\traits\Api;

use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\exception\ApiException;
use PSFS\base\types\Api;

trait MutationTableMapTrait
{
    /**
     * @return mixed
     */
    protected function getModelNamespace()
    {
        $tableMap = $this->getModelTableMap();
        if (null === $tableMap) {
            return null;
        }

        if (method_exists($tableMap, 'getOMClass')) {
            try {
                $legacyClassName = $tableMap::getOMClass(false);
                if (is_string($legacyClassName) && $legacyClassName !== '') {
                    return $legacyClassName;
                }
            } catch (\Throwable) {
                // Fall back to table map introspection below.
            }
        }
        try {
            $map = $tableMap::getTableMap();
            $className = $map?->getClassName();
            if (is_string($className) && $className !== '') {
                return $className;
            }
        } catch (\Throwable) {
            // Keep null when table map cannot be resolved in early runtime.
        }
        return null;
    }

    /**
     * @return TableMap|null
     */
    private function getTableMap()
    {
        $tableMapClass = $this->getModelTableMap();
        if (null === $tableMapClass) {
            return null;
        }
        try {
            return $tableMapClass::getTableMap();
        } catch (\Throwable) {
            if (method_exists($tableMapClass, 'buildTableMap')) {
                try {
                    $tableMapClass::buildTableMap();
                    return $tableMapClass::getTableMap();
                } catch (\Throwable) {
                    return null;
                }
            }
            return null;
        }
    }

    /**
     * @return array
     * @throws ApiException
     */
    protected function getPkDbName()
    {
        $tableMap = $this->requireTableMapForPrimaryKeys();
        $tableName = $this->resolveTableName($tableMap);
        $pks = $tableMap->getPrimaryKeys();
        $pkCount = count($pks);
        if ($pkCount === 1) {
            return $this->buildSinglePkMap($tableName, $pks);
        }
        if ($pkCount > 1) {
            return $this->buildCompositePkMap($tableName, $pks);
        }
        throw new ApiException(t('The API model is not properly mapped, there is no Primary Key or it is composite'));
    }

    /**
     * @throws ApiException
     */
    private function requireTableMapForPrimaryKeys(): TableMap
    {
        $tableMap = $this->getTableMap();
        if (!$tableMap instanceof TableMap) {
            throw new ApiException(t('The API model is not properly mapped, there is no Primary Key or it is composite'));
        }
        return $tableMap;
    }

    private function resolveTableName(TableMap $tableMap): string
    {
        $tableMapClass = get_class($tableMap);
        $tableName = (defined($tableMapClass . '::TABLE_NAME'))
            ? (string)constant($tableMapClass . '::TABLE_NAME')
            : '';
        if ($tableName === '' && method_exists($tableMap, 'getName')) {
            $tableName = (string)$tableMap->getName();
        }
        if ($tableName === '' && method_exists($tableMap, 'getPhpName')) {
            $tableName = (string)$tableMap->getPhpName();
        }
        return $tableName;
    }

    /**
     * @param array<int, ColumnMap> $primaryKeys
     * @return array<string, string>
     */
    private function buildSinglePkMap(string $tableName, array $primaryKeys): array
    {
        $pkKeys = array_keys($primaryKeys);
        $pkName = (string)($pkKeys[0] ?? '');

        return [
            $tableName . '.' . $pkName => Api::API_MODEL_KEY_FIELD,
        ];
    }

    /**
     * @param array<int, ColumnMap> $primaryKeys
     * @return array<string, string>
     */
    private function buildCompositePkMap(string $tableName, array $primaryKeys): array
    {
        $apiPks = [];
        $segments = [];
        foreach ($primaryKeys as $pk) {
            $apiPks[$tableName . '.' . $pk->getName()] = $pk->getPhpName();
            $segments[] = $tableName . '.' . $pk->getName();
        }
        $principal = $this->buildCompositePkExpression($segments);
        $apiPks[$principal] = Api::API_MODEL_KEY_FIELD;
        return $apiPks;
    }

    /**
     * @param array<int, string> $segments
     */
    private function buildCompositePkExpression(array $segments): string
    {
        if (count($segments) === 0) {
            return 'CONCAT()';
        }

        $glue = ', "' . Api::API_PK_SEPARATOR . '", ';
        return 'CONCAT(' . implode($glue, $segments) . ')';
    }
}
