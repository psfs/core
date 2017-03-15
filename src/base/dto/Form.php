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

    public function addField(Field $field)
    {
        $this->fields[] = $field;
    }

    public function __toArray()
    {
        $array = array();
        $array['fields'] = array();
        foreach ($this->fields as $field) {
            $array['fields'][] = $field->__toArray();
        }
        $array['actions'] = [];
        foreach($this->actions as $action) {
            $array['actions'][] = $action->__toArray();
        }
        return $array;
    }
}