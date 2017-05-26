<?php
namespace PSFS\base\types\helpers;

use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\dto\Field;
use PSFS\base\dto\Form;
use PSFS\base\Logger;
use PSFS\base\Router;

/**
 * Class ApiHelper
 * @package PSFS\base\types\helpers
 */
class ApiHelper
{
    /**
     * @param string $map
     * @param string $domain
     * @return Form
     */
    public static function generateFormFields($map, $domain)
    {
        $form = new Form();
        /** @var TableMap $tableMap */
        $tableMap = $map::getTableMap();
        $behaviors = $tableMap->getBehaviors();
        foreach ($map::getFieldNames() as $field) {
            $fDto = self::parseFormField($domain, $tableMap, $field, $behaviors);
            if(null !== $fDto) {
                $form->addField($fDto);
            }
        }

        if(array_key_exists('i18n', $behaviors)) {
            $relateI18n = $tableMap->getRelation($tableMap->getPhpName() . 'I18n');
            if(null !== $relateI18n) {
                $i18NTableMap = $relateI18n->getLocalTable();
                foreach($i18NTableMap->getColumns() as $columnMap) {
                    if(!$form->fieldExists($columnMap->getPhpName())) {
                        $fDto = self::parseFormField($domain, $i18NTableMap, $columnMap->getPhpName(), $i18NTableMap->getBehaviors());
                        if(null !== $fDto) {
                            $fDto->pk = false;
                            $form->addField($fDto);
                        }
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Extract the foreign relation field
     * @param ColumnMap $mappedColumn
     * @param string $field
     * @param string $domain
     * @return Field
     */
    public static function extractForeignModelsField(ColumnMap $mappedColumn, $field, $domain)
    {
        $fDto = new Field($field, _($field));
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
     * @param $field
     * @param string string $type
     * @param boolean $required
     * @return Field
     */
    private static function createField($field, $type = Field::TEXT_TYPE, $required = false)
    {
        $fDto = new Field($field, _($field));
        $fDto->type = $type;
        $fDto->required = $required;
        return $fDto;
    }

    /**
     * Extract primary key field
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generatePrimaryKeyField($field, $required = false)
    {
        $fDto = self::createField($field, Field::HIDDEN_TYPE, $required);
        $fDto->required = false;
        $fDto->pk = true;
        return $fDto;
    }

    /**
     * Extract numeric field
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generateNumericField($field, $required = false)
    {
        return self::createField($field, Field::NUMBER_TYPE, $required);
    }

    /**
     * Extract string fields
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generateStringField($field, $required = false)
    {
        return self::createField($field, Field::TEXT_TYPE, $required);
    }

    /**
     * Extract string fields
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generateBooleanField($field, $required = false)
    {
        return self::createField($field, Field::SWITCH_TYPE, $required);
    }

    /**
     * Extract string fields
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generatePasswordField($field, $required = false)
    {
        return self::createField($field, Field::PASSWORD_FIELD, $required);
    }

    /**
     * Extract date fields
     * @param string $field
     * @param boolean $required
     * @return Field
     */
    public static function generateDateField($field, $required = false)
    {
        return self::createField($field, Field::DATE, $required);
    }

    /**
     * @param $field
     * @param bool $required
     * @return Field
     */
    public static function generateEnumField($field, $required = false)
    {
        return self::createField($field, Field::COMBO_TYPE, $required);
    }

    /**
     * Check if parametrized field exists in api model
     * @param TableMap $tableMap
     * @param $field
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    public static function checkFieldExists(TableMap $tableMap, $field)
    {
        $column = null;
        try {
            $column = $tableMap->getColumnByPhpName($field);
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
            foreach($tableMap->getRelations() as $relation) {
                $column = self::checkFieldExists($relation->getLocalTable(), $field);
            }
        }
        return $column;
    }

    /**
     * @param ColumnMap $column
     * @param ModelCriteria $query
     * @param mixed $value
     */
    private static function addQueryFilter(ColumnMap $column, ModelCriteria &$query, $value = null)
    {
        $tableField = $column->getFullyQualifiedName();
        if (preg_match('/^<=/', $value)) {
            $query->add($tableField, substr($value, 2, strlen($value)), Criteria::LESS_EQUAL);
        } elseif (preg_match('/^<=/', $value)) {
            $query->add($tableField, substr($value, 1, strlen($value)), Criteria::LESS_EQUAL);
        } elseif (preg_match('/^>=/', $value)) {
            $query->add($tableField, substr($value, 2, strlen($value)), Criteria::GREATER_EQUAL);
        } elseif (preg_match('/^>/', $value)) {
            $query->add($tableField, substr($value, 1, strlen($value)), Criteria::GREATER_THAN);
        } elseif (preg_match('/^\[/', $value) && preg_match('/\]$/', $value)) {
            $query->add($tableField, explode(',', preg_replace('/(\[|\])/', '', $value)), Criteria::IN);
        } elseif (preg_match('/^(\'|\")(.*)(\'|\")$/', $value)) {
            $text = preg_replace('/(\'|\")/', '', $value);
            $text = preg_replace('/\ /', '%', $text);
            $query->add($tableField, '%' . $text . '%', Criteria::LIKE);
        } else {
            $query->add($tableField, $value, Criteria::EQUAL);
        }
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
        foreach ($extraColumns as $extra => $name) {
            $exp .= $sep . $extra;
            $sep = ', " ", ';
        }
        $exp .= ")";
        $text = preg_replace('/(\'|\")/', '', $value);
        $text = preg_replace('/\ /', '%', $text);
        $query->where($exp . Criteria::LIKE . '"%' . $text . '%"');
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
     * Method that extract
     * @param string $modelNameNamespace
     * @param ConnectionInterface $con
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public static function extractQuery($modelNameNamespace, ConnectionInterface $con = null)
    {
        $queryReflector = new \ReflectionClass($modelNameNamespace . "Query");
        /** @var \Propel\Runtime\ActiveQuery\ModelCriteria $query */
        $query = $queryReflector->getMethod('create')->invoke($con);

        return $query;
    }

    /**
     * @param string $domain
     * @param TableMap $tableMap
     * @param string $field
     * @param array $behaviors
     * @return null|Field
     */
    protected static function parseFormField($domain, $tableMap, $field, array $behaviors = [])
    {
        $fDto = null;
        /** @var ColumnMap $mappedColumn */
        $mappedColumn = $tableMap->getColumnByPhpName($field);
        $required = $mappedColumn->isNotNull() && null === $mappedColumn->getDefaultValue();
        if ($mappedColumn->isForeignKey()) {
            $fDto = self::extractForeignModelsField($mappedColumn, $field, $domain);
        } elseif ($mappedColumn->isPrimaryKey() && $required) {
            $fDto = self::generatePrimaryKeyField($field, $required);
        } elseif ($mappedColumn->isNumeric()) {
            $fDto = self::generateNumericField($field, $required);
        } elseif ($mappedColumn->isText()) {
            if ($mappedColumn->getSize() > 100) {
                $fDto = self::createField($field, Field::TEXTAREA_TYPE, $required);
            } else {
                $fDto = self::generateStringField($field, $required);
            }
        } elseif ($mappedColumn->getType() === PropelTypes::BOOLEAN) {
            $fDto = self::generateBooleanField($field, $required);
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::BINARY, PropelTypes::VARBINARY])) {
            $fDto = self::generatePasswordField($field, $required);
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::TIMESTAMP, PropelTypes::DATE, PropelTypes::BU_DATE, PropelTypes::BU_TIMESTAMP])) {
            $fDto = self::createField($field, $mappedColumn->getType() == PropelTypes::TIMESTAMP ? Field::TEXT_TYPE : Field::DATE, $required);
            if (array_key_exists('timestampable', $behaviors) && false !== array_search($mappedColumn->getName(), $behaviors['timestampable'])) {
                $fDto->required = false;
                $fDto->type = Field::TIMESTAMP;
            }
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::ENUM, PropelTypes::SET])) {
            $fDto = self::generateEnumField($field, $required);
            foreach ($mappedColumn->getValueSet() as $value) {
                $fDto->data[] = [
                    $field => $value,
                    "Label" => _($value),
                ];
            }
        }
        if (null !== $fDto) {
            $fDto->size = $mappedColumn->getSize();
            if ($mappedColumn->isPrimaryKey()) {
                $fDto->pk = true;
            }
        }
        return $fDto;
    }
}