<?php
namespace PSFS\base\types\helpers;

use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\Field;
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
     * @param string $tableMap
     * @param string $field
     * @param array $behaviors
     * @return Field|null
     * @throws \PSFS\base\exception\GeneratorException
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
                switch(Config::getParam('api.field.case', TableMap::TYPE_PHPNAME)) {
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
        }
        if (null !== $fDto) {
            $fDto->size = $mappedColumn->getSize();
            if ($mappedColumn->isPrimaryKey()) {
                $fDto->pk = true;
            }
        }
        switch(Config::getParam('api.field.case', TableMap::TYPE_PHPNAME)) {
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
        return $fDto;
    }
}