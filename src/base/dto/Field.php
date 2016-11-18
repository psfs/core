<?php
    namespace PSFS\base\dto;

    /**
     * Class Field
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

        /**
         * @var string label
         */
        public $label;
        /**
         * @var string name
         */
        public $name;
        /**
         * @var string type
         */
        public $type = Field::TEXT_TYPE;
        /**
         * @var array data
         */
        public $data = array();
        /**
         * @var string url
         */
        public $url;
        /**
         * @var Object value
         */
        public $value;
        /**
         * @var bool required
         */
        public $required = true;
        /**
         * @var string entity
         */
        public $entity;
        /**
         * @var bool pk
         */
        public $pk = false;

        public function __construct($name, $label, $type = Field::TEXT_TYPE, $value = null, $data = array(), $url = null, $required = true)
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