<?php
namespace PSFS\base\types\helpers;

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
     * Extract primary key field
     * @param $field
     * @return Field
     */
    public static function generatePrimaryKeyField($field)
    {
        $fDto = new Field($field, _($field));
        $fDto->type = Field::HIDDEN_TYPE;
        $fDto->required = false;
        return $fDto;
    }

    public static function generateNumericField($field)
    {
        $fDto = new Field($field, _($field));
        $fDto->type = Field::NUMBER_TYPE;
        return $fDto;
    }
}