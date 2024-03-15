<?php

namespace PSFS\base\types\helpers;

use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\Field;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\traits\Helper\FieldHelperTrait;
use PSFS\base\types\traits\Helper\FieldMapperHelperTrait;
use PSFS\base\types\traits\Helper\FieldModelHelperTrait;

/**
 * Class ApiHelper
 * @package PSFS\base\types\helpers
 */
class ApiHelper
{
    use FieldHelperTrait;
    use FieldModelHelperTrait;
    use FieldMapperHelperTrait;

    /**
     * @param string $domain
     * @param TableMap $tableMap
     * @param string $field
     * @param array $behaviors
     * @return Field|null
     * @throws GeneratorException
     */
    protected static function parseFormField($domain, TableMap $tableMap, $field, array $behaviors = [])
    {
        /** @var ColumnMap $mappedColumn */
        $mappedColumn = $tableMap->getColumnByPhpName($field);
        $required = $mappedColumn->isNotNull() && null === $mappedColumn->getDefaultValue();
        $fDto = self::parseFieldType($domain, $field, $behaviors, $mappedColumn, $required);
        if (null !== $fDto) {
            self::checkPrimaryKey($fDto, $mappedColumn);
            self::applyCaseToNames($fDto, $mappedColumn);
        }
        return $fDto;
    }

    /**
     * @param ColumnMap $mappedColumn
     * @param Field|null $fDto
     */
    protected static function applyCaseToNames(Field $fDto, ColumnMap $mappedColumn)
    {
        switch (Config::getParam('api.field.case', TableMap::TYPE_PHPNAME)) {
            default:
            case TableMap::TYPE_PHPNAME:
                $fDto->name = $mappedColumn->getPhpName();
                $fDto->label = t($mappedColumn->getPhpName());
                break;
            case TableMap::TYPE_CAMELNAME:
                $fDto->name = lcfirst($mappedColumn->getPhpName());
                $fDto->label = t(lcfirst($mappedColumn->getPhpName()));
                break;
            case TableMap::TYPE_COLNAME:
                $fDto->name = $mappedColumn->getFullyQualifiedName();
                $fDto->label = t($mappedColumn->getFullyQualifiedName());
                break;
        }
    }

    /**
     * @param Field|null $fDto
     * @param ColumnMap $mappedColumn
     */
    protected static function checkPrimaryKey(Field $fDto, ColumnMap $mappedColumn)
    {
        $fDto->size = $mappedColumn->getSize();
        if ($mappedColumn->isPrimaryKey()) {
            $fDto->pk = true;
        }
    }

    /**
     * @param $field
     * @param bool $required
     * @param ColumnMap $mappedColumn
     * @return Field
     * @throws GeneratorException
     */
    protected static function parseEnumField($field, bool $required, ColumnMap $mappedColumn)
    {
        $fDto = self::generateEnumField($field, $required);
        foreach ($mappedColumn->getValueSet() as $value) {
            switch (Config::getParam('api.field.case', TableMap::TYPE_PHPNAME)) {
                default:
                case TableMap::TYPE_PHPNAME:
                    $fieldName = $mappedColumn->getPhpName();
                    break;
                case TableMap::TYPE_CAMELNAME:
                    $fieldName = lcfirst($mappedColumn->getPhpName());
                    break;
                case TableMap::TYPE_COLNAME:
                    $fieldName = $mappedColumn->getFullyQualifiedName();
                    break;
            }
            $fDto->data[] = [
                $fieldName => $value,
                "Label" => t($value),
            ];
        }
        return $fDto;
    }

    /**
     * @param $domain
     * @param $field
     * @param array $behaviors
     * @param ColumnMap $mappedColumn
     * @param bool $required
     * @return Field
     * @throws GeneratorException
     */
    protected static function parseFieldType($domain, $field, array $behaviors, ColumnMap $mappedColumn, bool $required)
    {
        $fDto = null;
        if ($mappedColumn->isForeignKey()) {
            $fDto = self::extractForeignModelsField($mappedColumn, $field, $domain);
        } elseif ($mappedColumn->isPrimaryKey() && $required) {
            $fDto = self::generatePrimaryKeyField($field, $required);
        } elseif ($mappedColumn->isNumeric()) {
            $fDto = self::generateNumericField($field, $required);
        } elseif ($mappedColumn->isText()) {
            $fDto = self::generateTextField($field, $mappedColumn, $required);
        } elseif ($mappedColumn->getType() === PropelTypes::BOOLEAN) {
            $fDto = self::generateBooleanField($field, $required);
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::BINARY, PropelTypes::VARBINARY])) {
            $fDto = self::generatePasswordField($field, $required);
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::TIMESTAMP, PropelTypes::DATE, PropelTypes::BU_DATE, PropelTypes::BU_TIMESTAMP])) {
            $fDto = self::generateTimestampField($field, $behaviors, $mappedColumn, $required);
        } elseif (in_array($mappedColumn->getType(), [PropelTypes::ENUM, PropelTypes::SET])) {
            $fDto = self::parseEnumField($field, $required, $mappedColumn);
        }
        return $fDto;
    }

    /**
     * @param $field
     * @param ColumnMap $mappedColumn
     * @param bool $required
     * @return Field
     * @throws GeneratorException
     */
    protected static function generateTextField($field, ColumnMap $mappedColumn, bool $required)
    {
        if ($mappedColumn->getSize() > 100) {
            $fDto = self::createField($field, Field::TEXTAREA_TYPE, $required);
        } else {
            $fDto = self::generateStringField($field, $required);
        }
        return $fDto;
    }

    /**
     * @param $field
     * @param array $behaviors
     * @param ColumnMap $mappedColumn
     * @param bool $required
     * @return Field
     * @throws GeneratorException
     */
    protected static function generateTimestampField($field, array $behaviors, ColumnMap $mappedColumn, bool $required)
    {
        $fDto = self::createField($field, $mappedColumn->getType() == PropelTypes::TIMESTAMP ? Field::TEXT_TYPE : Field::DATE, $required);
        if (array_key_exists('timestampable', $behaviors) && false !== array_search($mappedColumn->getName(), $behaviors['timestampable'])) {
            $fDto->required = false;
            $fDto->type = Field::TIMESTAMP;
        }
        return $fDto;
    }
}
