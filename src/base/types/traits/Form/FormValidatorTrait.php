<?php

namespace PSFS\base\types\traits\Form;

/**
 * Trait FormGeneratorTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormValidatorTrait
{
    use FormSchemaTrait;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Método que añade un error para un campo del formulario
     * @param string $field
     * @param string $error
     *
     * @return FormSchemaTrait
     */
    public function setError($field, $error = 'Error de validación')
    {
        $this->fields[$field]['error'] = $error;
        $this->errors[$field] = $error;
        return $this;
    }

    /**
     * @param string $field
     *
     * @return string
     */
    public function getError($field)
    {
        return array_key_exists($field, $this->errors) ? $this->errors[$field] : '';
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function checkEmpty($value)
    {
        $isEmpty = false;
        // NULL check
        if (null === $value) {
            $isEmpty = true;
            // Empty Array check
        } else if (is_array($value) && 0 === count($value)) {
            $isEmpty = true;
            // Empty string check
        } else if ('' === preg_replace('/(\ |\r|\n)/m', '', $value)) {
            $isEmpty = true;
        }

        return $isEmpty;
    }

    /**
     * @param array $field
     * @param string $key
     * @return array
     */
    private function checkFieldValidation($field, $key)
    {
        // Check if required
        $valid = true;
        if ((!array_key_exists('required', $field) || false !== (bool)$field['required']) && $this->checkEmpty($field['value'])) {
            $this->setError($key, str_replace('%s', "<strong>{$key}</strong>", t('El campo %s es oligatorio')));
            $field['error'] = $this->getError($key);
            $valid = false;
        }
        // Check validations
        if (array_key_exists('pattern', $field)
            && array_key_exists($key, $field)
            && !array_key_exists('error', $field[$key])
            && !empty($field['value'])
            && preg_match('/' . $field['pattern'] . '/', $field['value']) === 0
        ) {
            $this->setError($key, str_replace('%s', "<strong>{$key}</strong>", t('El campo %s no tiene un formato válido')));
            $field['error'] = $this->getError($key);
            $valid = false;
        }
        return array($field, $valid);
    }
}
