<?php

namespace PSFS\base\dto;

/**
 * Class Form
 * @package PSFS\base\dto
 */
class Form extends Dto
{
    /**
     * @var \PSFS\base\dto\Field[]
     */
    private $fields = [];
    /**
     * @var \PSFS\base\dto\FormAction[]
     */
    public $actions = [];

    /**
     * @param Field $field
     */
    public function addField(Field $field)
    {
        $this->fields[$field->name] = $field;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function fieldExists($name)
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * @return array
     */
    public function __toArray()
    {
        $array = [
            'fields' => [],
            'actions' => [],
        ];
        foreach ($this->fields as $field) {
            $array['fields'][] = $field->__toArray();
        }
        usort($array['fields'], function ($fieldA, $fieldB) {
            if ((bool)$fieldA['required'] !== (bool)$fieldB['required']) {
                if ((bool)$fieldA['required']) {
                    return -1;
                } else {
                    return 1;
                }
            }
            $aOrder = Form::fieldsOrder($fieldA);
            $bOrder = Form::fieldsOrder($fieldB);
            if ($aOrder === $bOrder) {
                return strcmp($fieldA['name'], $fieldB['name']);
            }
            return ($aOrder < $bOrder) ? -1 : 1;
        });
        foreach ($this->actions as $action) {
            $array['actions'][] = $action->__toArray();
        }
        return $array;
    }

    /**
     * @param array $field
     * @return int
     */
    public static function fieldsOrder(array $field)
    {
        $type = $field['type'] ?? '';
        if ($type === Field::HIDDEN_TYPE) {
            return 0;
        }
        if (in_array($type, [Field::TEXT_TYPE, Field::PHONE_TYPE, Field::URL_TYPE, Field::PASSWORD_FIELD, Field::SEARCH_TYPE], true)) {
            return 1;
        }
        if (in_array($type, [Field::CHECK_TYPE, Field::RADIO_TYPE, Field::COMBO_TYPE, Field::NUMBER_TYPE, Field::SWITCH_TYPE], true)) {
            return 2;
        }
        if ($type === Field::TEXTAREA_TYPE) {
            return 3;
        }
        return 4;
    }
}
