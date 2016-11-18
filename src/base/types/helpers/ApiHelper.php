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
            if ($mappedColumn->isForeignKey()) {
                $fDto = self::extractForeignModelsField($mappedColumn, $field);
            } elseif ($mappedColumn->isPrimaryKey()) {
                $fDto = self::generatePrimaryKeyField($field);
            } elseif ($mappedColumn->isNumeric()) {
                $fDto = self::generateNumericField($field);
            } elseif ($mappedColumn->isText()) {
                $fDto = self::generateStringField($field);
            } elseif($mappedColumn->getType() === PropelTypes::BOOLEAN) {
                $fDto = self::generateBooleanField($field);
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
     * @param string $type
     * @return Field
     */
    private static function createField($field, $type = Field::TEXT_TYPE)
    {
        $fDto = new Field($field, _($field));
        $fDto->type = $type;
        return $fDto;
    }

    /**
     * Extract primary key field
     * @param $field
     * @return Field
     */
    public static function generatePrimaryKeyField($field)
    {
        $fDto = self::createField($field, Field::HIDDEN_TYPE);
        $fDto->required = false;
        $fDto->pk = true;
        return $fDto;
    }

    /**
     * Extract numeric field
     * @param $field
     * @return Field
     */
    public static function generateNumericField($field)
    {
        return self::createField($field, Field::NUMBER_TYPE);
    }

    /**
     * Extract string fields
     * @param $field
     * @return Field
     */
    public static function generateStringField($field)
    {
        return self::createField($field);
    }

    /**
     * Extract string fields
     * @param $field
     * @return Field
     */
    public static function generateBooleanField($field)
    {
        return self::createField($field, Field::SWITCH_TYPE);
    }
}