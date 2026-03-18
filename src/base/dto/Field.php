<?php

namespace PSFS\base\dto;

/**
 * @package PSFS\base\dto
 */
class Field extends Dto
{
    const TEXT_TYPE = 'text';
    const PHONE_TYPE = 'tel';
    const URL_TYPE = 'url';
    const CHECK_TYPE = 'checkbox';
    const RADIO_TYPE = 'radio';
    const COMBO_TYPE = 'select';
    const TEXTAREA_TYPE = 'textarea';
    const SEARCH_TYPE = 'search';
    const HIDDEN_TYPE = 'hidden';
    const NUMBER_TYPE = 'number';
    const SWITCH_TYPE = 'switch';
    const PASSWORD_FIELD = 'password';
    const DATE = 'date';
    const TIMESTAMP = 'timestamp';

    /**
     * @var string
 */
    public $label;
    /**
     * @var string
 */
    public $name;
    /**
     * @var string
 */
    public $type = Field::TEXT_TYPE;
    /**
     * @var array
 */
    public $data = array();
    /**
     * @var string
 */
    public $url;
    /**
     * @var Object
 */
    public $value;
    /**
     * @var bool
 */
    public $required = true;
    /**
     * @var string
 */
    public $entity;
    /**
     * @var bool
 */
    public $readonly = false;
    /**
     * @var bool
 */
    public $pk = false;
    /**
     * @var string
 */
    public $relatedField;
    /**
     * @var integer
 */
    public $size;

    public function __construct($name, $label, $type = self::TEXT_TYPE, $value = null, $data = array(), $url = null, $required = true)
    {
        $this->name = $name;
        $this->label = $label;
        $this->type = $type;
        $this->value = $value;
        $this->data = $data;
        $this->url = $url;
        $this->required = $required;
    }
}
