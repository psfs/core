<?php

namespace PSFS\base\types\traits\Form;

use PSFS\base\exception\FormException;
use PSFS\base\Request;
use PSFS\base\types\interfaces\FormType;

/**
 * @package PSFS\base\types\traits\Form
 */
trait FormDataTrait
{
    use FormValidatorTrait;

    /**
     * Contract: provided by FormType implementations.
     */
    abstract public function getName();

    /**
     * @var array
     */
    protected $fields = [];
    /**
     * @var array
     */
    protected $extra = [];

    /**
     * @param $name
     * @param array $value
     * @return $this
     */
    public function add($name, array $value = [])
    {
        $this->fields[$name] = $value;
        $this->fields[$name]['name'] = $this->getName() . '[' . $name . ']';
        $this->fields[$name]['id'] = $this->getName() . '_' . $name;
        $this->fields[$name]['placeholder'] = array_key_exists('placeholder', $value) ? $value['placeholder'] : $name;
        $this->fields[$name]['hasLabel'] = array_key_exists('hasLabel', $value) ? $value['hasLabel'] : true;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return array|null
     */
    public function getField($name)
    {
        return (null !== $name && array_key_exists($name, $this->fields)) ? $this->fields[$name] : null;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function getFieldValue($name)
    {
        $value = null;
        $field = $this->getField($name);
        if (null !== $this->getField($name)) {
            $value = (array_key_exists('value', $field) && null !== $field['value']) ? $field['value'] : null;
        }
        return $value;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $data = array();
        $tokenField = $this->getName() . '_token';
        $tokenKeyField = $this->getName() . '_token_key';
        if (!empty($this->fields)) {
            foreach ($this->fields as $key => $field) {
                if (FormType::SEPARATOR !== $key && $key !== $tokenField && $key !== $tokenKeyField) {
                    $data[$key] = array_key_exists('value', $field) ? $field['value'] : null;
                }
            }
        }
        return $data;
    }


    /**
     * @return array
     */
    public function getExtraData()
    {
        return $this->extra ?: array();
    }

    /**
     * @param array $data
     * @return void
     * @throws FormException
     */
    public function setData(array $data = [])
    {
        if (empty($this->fields)) {
            throw new FormException(t('Form fields must be configured first'), 400);
        }

        foreach ($this->fields as $key => &$field) {
            if (array_key_exists($key, $data)) {
                $field['value'] = $data[$key];
            }
        }
    }

    /**
     * @param array $data
     * @param string $formName
     * @param string $key
     * @param string|array $field
     * @return array
     */
    private function hydrateField($data, $formName, $key, $field)
    {
        if (array_key_exists($key, $data[$formName])) {
            if (preg_match(
                    '/id/i',
                    $key
                ) && ($data[$formName][$key] === 0 || $data[$formName][$key] === '%' || $data[$formName][$key] === '')) {
                $field['value'] = null;
            } else {
                $field['value'] = $data[$formName][$key];
            }
        } else {
            unset($field['value']);
        }
        return array($data, $field);
    }

    /**
     * @return FormSchemaTrait
     */
    public function hydrate()
    {
        $data = Request::getInstance()->getData() ?: [];
        // Feeding data from request
        $formName = $this->getName();
        if (array_key_exists($formName, $data)) {
            foreach ($this->fields as $key => &$field) {
                list($data, $field) = $this->hydrateField($data, $formName, $key, $field);
            }
            // Clean data
            unset($data[$formName]);
        }
        // Load extra data
        $this->extra = $data;
        return $this;
    }
}
