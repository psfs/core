<?php

namespace PSFS\base\types\traits\Api;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\types\helpers\ApiHelper;

trait MutationI18nTrait
{
    protected function hasI18nQuerySupport(ModelCriteria $query): bool
    {
        return method_exists($query, 'useI18nQuery');
    }

    protected function resolveI18nMapClassName(string $modelNamespace): string
    {
        $modelI18n = $modelNamespace . 'I18n';
        $modelParts = explode('\\', $modelI18n);
        return str_replace(end($modelParts), 'Map\\' . end($modelParts), $modelI18n) . 'TableMap';
    }

    protected function appendI18nColumnsToQuery(ModelCriteria $query, TableMap $i18nTableMap, string $lang): void
    {
        foreach ($i18nTableMap->getColumns() as $columnMap) {
            if (!$columnMap instanceof ColumnMap) {
                continue;
            }
            if (!$columnMap->isPrimaryKey()) {
                $query->withColumn($columnMap->getFullyQualifiedName(), ApiHelper::getColumnMapName($columnMap));
                continue;
            }
            if (!$columnMap->isForeignKey()) {
                $query->withColumn(
                    'IFNULL(' . $columnMap->getFullyQualifiedName() . ', "' . $lang . '")',
                    ApiHelper::getColumnMapName($columnMap)
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function applyI18nFieldsToModel(
        ActiveRecordInterface $model,
        TableMap $baseTableMap,
        array $data,
        string $lang
    ): void {
        $relationName = $baseTableMap->getPhpName() . 'I18n';
        if (!$baseTableMap->hasRelation($relationName)) {
            return;
        }
        $relation = $baseTableMap->getRelation($relationName);
        $i18nTableMap = $relation->getLocalTable();
        $model->setLocale($lang);
        foreach ($i18nTableMap->getColumns() as $columnMap) {
            if (!$columnMap instanceof ColumnMap) {
                continue;
            }
            if ($columnMap->isPrimaryKey() && $columnMap->isForeignKey()) {
                continue;
            }
            $method = 'set' . $columnMap->getPhpName();
            $dtoColumnName = ApiHelper::getColumnMapName($columnMap);
            if (!array_key_exists($dtoColumnName, $data) || !method_exists($model, $method)) {
                continue;
            }
            $model->$method($data[$dtoColumnName]);
        }
    }
}

