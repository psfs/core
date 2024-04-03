<?php

namespace PSFS\base\types\traits\Helper;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\DataFetcher\ArrayDataFetcher;
use Propel\Runtime\Formatter\ObjectFormatter;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;
use PSFS\base\types\Api;

/**
 * Trait FieldMapperHelperTrait
 * @package PSFS\base\types\traits\Helper
 */
trait FieldMapperHelperTrait
{

    /**
     * @param ActiveRecordInterface $model
     * @param array $data
     * @return array
     */
    private static function mapResult(ActiveRecordInterface $model, array $data = [])
    {
        $result = [];
        foreach ($data as $key => $value) {
            try {
                $realValue = $model->getByName($key);
            } catch (\Exception $e) {
                $realValue = $value;
            }
            if (Api::API_MODEL_KEY_FIELD === $key) {
                $result[$key] = (integer)$realValue;
            } else {
                $result[$key] = $realValue;
            }
        }
        return $result;
    }

    /**
     * @param string $namespace
     * @param ColumnMap $modelPk
     * @param array $query
     * @param array|ActiveRecordInterface $data
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function mapArrayObject($namespace, ColumnMap $modelPk, array $query, array|ActiveRecordInterface $data = [])
    {
        $formatter = new ObjectFormatter();
        $formatter->setClass($namespace);
        $data[$modelPk->getPhpName()] = $data[Api::API_MODEL_KEY_FIELD];
        $dataFetcher = new ArrayDataFetcher($data);
        $formatter->setDataFetcher($dataFetcher);
        /** @var TableMapTrait $objTableMap */
        $objTableMap = get_class($formatter->getTableMap());
        $objData = is_array($data) ? $data : $data->toArray();
        foreach ($objTableMap::getFieldNames() as $field) {
            if (!array_key_exists($field, $objData)) {
                $objData[$field] = null;
            }
        }
        /** @var ActiveRecordInterface $obj */
        $obj = @$formatter->getAllObjectsFromRow($objData);
        $result = self::mapResult($obj, $data);
        if (!preg_match('/' . $modelPk->getPhpName() . '/i', $query[Api::API_FIELDS_RESULT_FIELD])) {
            unset($result[$modelPk->getPhpName()]);
        }
        return $result;
    }

    /**
     * @param ColumnMap $field
     * @return string
     */
    public static function getColumnMapName(ColumnMap $field)
    {
        switch (self::getFieldTypes()) {
            default:
            case 'UpperCamelCase':
            case TableMap::TYPE_PHPNAME:
                $columnName = $field->getPhpName();
                break;
            case 'camelCase':
            case 'lowerCamelCase':
            case TableMap::TYPE_CAMELNAME:
                $columnName = lcfirst($field->getPhpName());
                break;
            case 'dbColumn':
            case TableMap::TYPE_COLNAME:
                $columnName = $field->getFullyQualifiedName();
                break;
            case TableMap::TYPE_FIELDNAME:
                $columnName = $field->getName();
                break;
        }
        return $columnName;
    }
}
