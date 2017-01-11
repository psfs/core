<?php
namespace PSFS\base\types\helpers;

use Propel\Generator\Model\PropelTypes;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\dto\Field;
use PSFS\base\dto\Form;
use PSFS\base\Router;

/**
 * Class ApiHelper
 * @package PSFS\base\types\helpers
 */
class ApiHelper
{
    /**
     * @param string $map
     * @return Form
     */
    public static function generateFormFields($map)
    {
        $form = new Form();
        /** @var TableMap $fields */
        $fields = $map::getTableMap();
        foreach ($map::getFieldNames() as $field) {
            $fDto = null;
            /** @var ColumnMap $mappedColumn */
            $mappedColumn = $fields->getColumnByPhpName($field);
            $required = $mappedColumn->isNotNull() && null === $mappedColumn->getDefaultValue();
            if ($mappedColumn->isForeignKey()) {
                $fDto = self::extractForeignModelsField($mappedColumn, $field);
            } elseif ($mappedColumn->isPrimaryKey()) {
                $fDto = self::generatePrimaryKeyField($field, $required);
            } elseif ($mappedColumn->isNumeric()) {
                $fDto = self::generateNumericField($field, $required);
            } elseif ($mappedColumn->isText()) {
                $fDto = self::generateStringField($field, $required);
            } elseif ($mappedColumn->getType() === PropelTypes::BOOLEAN) {
                $fDto = self::generateBooleanField($field, $required);
            } elseif (in_array($mappedColumn->getType(), [PropelTypes::BINARY, PropelTypes::VARBINARY])) {
                $fDto = self::generatePasswordField($field, $required);
            } elseif (in_array($mappedColumn->getType(), [PropelTypes::TIMESTAMP])) {
                //$fDto = self::generateDateField($field, $required);
            } elseif(in_array($mappedColumn->getType(), [PropelTypes::ENUM, PropelTypes::SET])) {
                $fDto = self::generateEnumField($field, $required);
                foreach($mappedColumn->getValueSet() as $value) {
                    $fDto->data[] = [
                        $field => $value,
                        "Label" => _($value),
                    ];
                }
            }

            if(null !== $fDto) {
                $form->addField($fDto);
            }
        }
        return $form;
    }

    /**
     * Extract the foreign relation field
     * @param ColumnMap $mappedColumn
     * @param $field
     * @return Field
     */
    public static function extractForeignModelsField(ColumnMap $mappedColumn, $field)
    {
        $fDto = new Field($field, _($field));
        $fDto->type = Field::COMBO_TYPE;
        $fDto->required = $mappedColumn->isNotNull();
        $relatedModel = strtolower($mappedColumn->getRelation()->getForeignTable()->getPhpName());
        $fDto->entity = $relatedModel;
        $fDto->url = Router::getInstance()->getRoute('api-' . $relatedModel . '-pk');
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
    public static function generateEnumField($field, $required = false) {
        return self::createField($field, Field::COMBO_TYPE, $required);
    }
}