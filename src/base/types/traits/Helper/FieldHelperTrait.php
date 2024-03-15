<?php

namespace PSFS\base\types\traits\Helper;

use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\Field;
use PSFS\base\dto\Form;
use PSFS\base\Logger;

/**
 * Trait FieldHelperTrait
 * @package PSFS\base\types\traits\Helper
 */
trait FieldHelperTrait
{

    /**
     * @param string $field
     * @param string $type
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function createField($field, $type = Field::TEXT_TYPE, $required = false)
    {
        $fDto = new Field($field, t($field));
        $fDto->type = $type;
        $fDto->required = $required;
        return $fDto;
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generatePrimaryKeyField($field, $required = false)
    {
        $fDto = self::createField($field, Field::HIDDEN_TYPE, $required);
        $fDto->required = false;
        $fDto->pk = true;
        return $fDto;
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateNumericField($field, $required = false)
    {
        return self::createField($field, Field::NUMBER_TYPE, $required);
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateStringField($field, $required = false)
    {
        return self::createField($field, Field::TEXT_TYPE, $required);
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateBooleanField($field, $required = false)
    {
        return self::createField($field, Field::SWITCH_TYPE, $required);
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generatePasswordField($field, $required = false)
    {
        return self::createField($field, Field::PASSWORD_FIELD, $required);
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateDateField($field, $required = false)
    {
        return self::createField($field, Field::DATE, $required);
    }

    /**
     * @param string $field
     * @param bool $required
     * @return Field
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateEnumField($field, $required = false)
    {
        return self::createField($field, Field::COMBO_TYPE, $required);
    }

    /**
     * @param string $map
     * @param string $domain
     * @return Form
     * @throws \Exception
     */
    public static function generateFormFields($map, $domain)
    {
        $form = new Form(false);
        /** @var TableMap $tableMap */
        $tableMap = $map::getTableMap();
        $behaviors = $tableMap->getBehaviors();
        foreach ($map::getFieldNames() as $field) {
            $fDto = self::parseFormField($domain, $tableMap, $field, $behaviors);
            if (null !== $fDto) {
                $form->addField($fDto);
            }
        }

        if (array_key_exists('i18n', $behaviors)) {
            $relateI18n = $tableMap->getRelation($tableMap->getPhpName() . 'I18n');
            if (null !== $relateI18n) {
                $i18NTableMap = $relateI18n->getLocalTable();
                foreach ($i18NTableMap->getColumns() as $columnMap) {
                    $columnName = self::getColumnMapName($columnMap);
                    if (!$form->fieldExists($columnName)) {
                        /** @var Field $fDto */
                        $fDto = self::parseFormField($domain, $i18NTableMap, $columnMap->getPhpName(), $i18NTableMap->getBehaviors());
                        if (null !== $fDto) {
                            $fDto->pk = false;
                            $fDto->required = true;
                            if (strtolower($fDto->name) === 'locale') {
                                $fDto->type = Field::COMBO_TYPE;
                                $languages = explode(',', Config::getParam('i18n.locales', Config::getParam('default.language', 'es_ES')));
                                foreach ($languages as $language) {
                                    $fDto->data[] = [
                                        $fDto->name => $language,
                                        'Label' => t($language),
                                    ];
                                }
                            }
                            $form->addField($fDto);
                        }
                    }
                }
            }
        }

        return $form;
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
            foreach ($tableMap->getColumns() as $tableMapColumn) {
                $columnName = $tableMapColumn->getPhpName();
                if (preg_match('/^' . $field . '$/i', $columnName)) {
                    $column = $tableMapColumn;
                    break;
                }
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_DEBUG);
        }
        return $column;
    }

    /**
     * @return string
     */
    public static function getFieldTypes()
    {
        $configType = Config::getParam('api.field.case', TableMap::TYPE_PHPNAME);
        switch ($configType) {
            default:
            case 'UpperCamelCase':
            case TableMap::TYPE_PHPNAME:
                $fieldType = TableMap::TYPE_PHPNAME;
                break;
            case 'camelCase':
            case 'lowerCamelCase':
            case TableMap::TYPE_CAMELNAME:
                $fieldType = TableMap::TYPE_CAMELNAME;
                break;
            case 'dbColumn':
            case TableMap::TYPE_COLNAME:
                $fieldType = TableMap::TYPE_COLNAME;
                break;
            case TableMap::TYPE_FIELDNAME:
                $fieldType = TableMap::TYPE_FIELDNAME;
                break;
            case TableMap::TYPE_NUM:
                $fieldType = TableMap::TYPE_NUM;
                break;
        }
        return $fieldType;
    }
}
