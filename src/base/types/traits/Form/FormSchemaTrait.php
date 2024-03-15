<?php

namespace PSFS\base\types\traits\Form;

/**
 * Trait FormSchemaTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormSchemaTrait
{
    /**
     * @var string
     */
    protected $method = 'POST';
    /**
     * @var string
     */
    protected $action = '';
    /**
     * @var string
     */
    protected $enctype = 'application/x-www-form-urlencoded';
    /**
     * @var array
     */
    protected $attrs = [];

    /**
     * Setters
     * @param string $enctype
     * @return FormSchemaTrait
     */
    public function setEncType($enctype)
    {
        $this->enctype = $enctype;
        return $this;
    }

    /**
     * @param $action
     * @return FormSchemaTrait
     */
    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @param $method
     * @return FormSchemaTrait
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param array $attrs
     * @return FormSchemaTrait
     */
    public function setAttrs(array $attrs)
    {
        $this->attrs = $attrs;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncType()
    {
        return $this->enctype;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return array
     */
    public function getAttrs()
    {
        return $this->attrs;
    }
}
