<?php
namespace PSFS\base\dto;

/**
 * Class Form
 * @package PSFS\base\dto
 */
class Form extends Dto
{
    /**
     * @var array fields
     */
    private $fields = [];
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
    public function fieldExists($name) {
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
        usort($array['fields'], function($fA, $fB) {
            if((bool)$fA['required'] !== (bool)$fB['required']) {
                if((bool)$fa['required']) {
                    return -1;
                } else {
                    return 1;
                }
            }
            $aOrder = Form::fieldsOrder($fA);
            $bOrder = Form::fieldsOrder($fB);
            if ($aOrder === $bOrder) {
                return strcmp($fA['name'], $fB['name']);
            }
            return ($aOrder < $bOrder) ? -1 : 1;
        });
        foreach($this->actions as $action) {
            $array['actions'][] = $action->__toArray();
        }
        return $array;
    }

    /**
     * @param array $field
     * @return int
     */
    public static function fieldsOrder(array $field) {
        switch ($field['type']) {
            case Field::TEXT_TYPE: $order = 1; break;
            case Field::PHONE_TYPE: $order = 1; break;
            case Field::URL_TYPE: $order = 1; break;
            case Field::CHECK_TYPE: $order = 2; break;
            case Field::RADIO_TYPE: $order = 2; break;
            case Field::COMBO_TYPE: $order = 2; break;
            case Field::TEXTAREA_TYPE: $order = 3; break;
            case Field::SEARCH_TYPE: $order = 1; break;
            case Field::HIDDEN_TYPE: $order = 0; break;
            case Field::NUMBER_TYPE: $order = 2; break;
            case Field::SWITCH_TYPE: $order = 2; break;
            case Field::PASSWORD_FIELD: $order = 1; break;
            case Field::DATE: $order = 4; break;
            case Field::TIMESTAMP: $order = 4; break;
            default: $order = 5; break;
        }
        return $order;
    }
}