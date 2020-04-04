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
        usort($array['fields'], function($fieldA, $fieldB) {
            if((bool)$fieldA['required'] !== (bool)$fieldB['required']) {
                if((bool)$fieldA['required']) {
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
            case Field::HIDDEN_TYPE: $order = 0; break;
            case Field::TEXT_TYPE:
            case Field::PHONE_TYPE:
            case Field::URL_TYPE:
            case Field::PASSWORD_FIELD:
            case Field::SEARCH_TYPE: $order = 1; break;
            case Field::CHECK_TYPE:
            case Field::RADIO_TYPE:
            case Field::COMBO_TYPE:
            case Field::NUMBER_TYPE:
            case Field::SWITCH_TYPE: $order = 2; break;
            case Field::TEXTAREA_TYPE: $order = 3; break;
            default:
            case Field::DATE:
            case Field::TIMESTAMP: $order = 4; break;
        }
        return $order;
    }
}
