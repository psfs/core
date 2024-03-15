<?php

namespace PSFS\base\types\traits\Helper;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\dto\Field;
use PSFS\base\Router;

/**
 * Trait FieldModelHelperTrait
 * @package PSFS\base\types\traits\Helper
 */
trait FieldModelHelperTrait
{
    /**
     * @param ColumnMap $mappedColumn
     * @param string $field
     * @param string $domain
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function extractForeignModelsField(ColumnMap $mappedColumn, $field, $domain)
    {
        $fDto = new Field($field, t($field));
        $fDto->type = Field::COMBO_TYPE;
        $fDto->required = $mappedColumn->isNotNull();
        $foreignTable = $mappedColumn->getRelation()->getForeignTable();
        $relatedModel = strtolower($foreignTable->getPhpName());
        $fDto->entity = $relatedModel;
        $relatedField = $foreignTable->getColumn($mappedColumn->getRelatedColumnName());
        $fDto->relatedField = $relatedField->getPhpName();
        $fDto->url = Router::getInstance()->getRoute(strtolower($domain) . '-api-' . $relatedModel);
        return $fDto;
    }

    /**
     * @param ColumnMap $column
     * @param ModelCriteria $query
     * @param mixed $value
     */
    private static function addQueryFilter(ColumnMap $column, ModelCriteria &$query, $value = null)
    {
        $tableField = $column->getFullyQualifiedName();
        if (is_array($value)) {
            $query->add($tableField, $value, Criteria::IN);
        } elseif (preg_match('/^\[/', $value) && preg_match('/\]$/', $value)) {
            $query->add($tableField, explode(',', preg_replace('/(\[|\])/', '', $value)), Criteria::IN);
        } elseif (preg_match('/^(\'|\")(.*)(\'|\")$/', $value)) {
            $text = preg_replace('/(\'|\")/', '', $value);
            $text = preg_replace('/\ /', '%', $text);
            $query->add($tableField, '%' . $text . '%', Criteria::LIKE);
        } else {
            if (null !== $column->getValueSet()) {
                $valueSet = $column->getValueSet();
                if (in_array($value, $valueSet)) {
                    $value = array_search($value, $valueSet);
                }
            }
            $query->add($tableField, $value);
        }
    }

    /**
     * Method that adds the fields for the model into the API Query
     * @param TableMap $tableMap
     * @param ModelCriteria $query
     * @param string $field
     * @param mixed $value
     */
    public static function addModelField(TableMap $tableMap, ModelCriteria &$query, $field, $value = null)
    {
        if ($column = self::checkFieldExists($tableMap, $field)) {
            self::addQueryFilter($column, $query, $value);
        }
    }

    /**
     * @param string $modelNameNamespace
     * @param ConnectionInterface|null $con
     * @return ModelCriteria
     * @throws \ReflectionException
     */
    public static function extractQuery($modelNameNamespace, ConnectionInterface $con = null)
    {
        $queryReflector = new \ReflectionClass($modelNameNamespace . "Query");
        /** @var \Propel\Runtime\ActiveQuery\ModelCriteria $query */
        $query = $queryReflector->getMethod('create')->invoke($con);

        return $query;
    }

    /**
     * @param TableMap $tableMap
     * @param ModelCriteria $query
     * @param array $extraColumns
     * @param mixed $value
     */
    public static function composerComboField(TableMap $tableMap, ModelCriteria &$query, array $extraColumns = [], $value = null)
    {
        $exp = 'CONCAT(';
        $sep = '';
        foreach ($tableMap->getColumns() as $column) {
            if ($column->isText()) {
                $exp .= $sep . 'IFNULL(' . $column->getFullyQualifiedName() . ',"")';
                $sep = ', " ", ';
            }
        }
        foreach ($tableMap->getRelations() as $relation) {
            if (preg_match('/I18n$/i', $relation->getName())) {
                $localeTableMap = $relation->getLocalTable();
                foreach ($localeTableMap->getColumns() as $column) {
                    if ($column->isText()) {
                        $exp .= $sep . 'IFNULL(' . $column->getFullyQualifiedName() . ',"")';
                        $sep = ', " ", ';
                    }
                }
            }
        }
        foreach (array_keys($extraColumns) as $extra) {
            if (!preg_match("/(COUNT|DISTINCT|SUM|MAX|MIN|GROUP)/i", $extra)) {
                $exp .= $sep . $extra;
                $sep = ', " ", ';
            }
        }
        $exp .= ")";
        $text = preg_replace('/(\'|\")/', '', $value);
        $text = preg_replace('/\ /', '%', $text);
        $query->where($exp . Criteria::LIKE . '"%' . $text . '%"');
    }

    /**
     * @param TableMap $tableMap
     * @return null|ColumnMap
     */
    public static function extractPrimaryKeyColumnName(TableMap $tableMap)
    {
        $modelPk = null;
        foreach ($tableMap->getPrimaryKeys() as $pk) {
            $modelPk = $pk;
            break;
        }
        return $modelPk;
    }
}
