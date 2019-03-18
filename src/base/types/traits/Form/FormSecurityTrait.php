<?php
namespace PSFS\base\types\traits\Form;

use PSFS\base\types\Form;

/**
 * Trait FormSecurityTrait
 * @package PSFS\base\types\traits\Form
 */
trait FormSecurityTrait {
    /**
     * @var
     */
    protected $crfs;

    /**
     * Método que genera un CRFS token para los formularios
     * @return Form
     */
    private function genCrfsToken()
    {
        $hashOrig = '';
        if (!empty($this->fields)) {
            foreach (array_keys($this->fields) as $field) {
                if ($field !== self::SEPARATOR) {
                    $hashOrig .= $field;
                }
            }
        }
        if ('' !== $hashOrig) {
            $this->crfs = sha1($hashOrig);
            $this->add($this->getName() . '_token', array(
                'type' => 'hidden',
                'value' => $this->crfs,
            ));
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $valid = true;
        $tokenField = $this->getName() . '_token';
        // Check crfs token
        if (!$this->existsFormToken($tokenField)) {
            $this->errors[$tokenField] = t('Formulario no válido');
            $this->fields[$tokenField]['error'] = $this->errors[$tokenField];
            $valid = false;
        }
        // Validate all the fields
        if ($valid && count($this->fields) > 0) {
            foreach ($this->fields as $key => &$field) {
                if ($key === $tokenField || $key === self::SEPARATOR) {
                    continue;
                }
                list($field, $valid) = $this->checkFieldValidation($field, $key);
            }
        }
        return $valid;
    }

    /**
     * @param string $tokenField
     * @return bool
     */
    protected function existsFormToken($tokenField)
    {
        if ($this->method !== 'POST') {
            return true;
        }
        if (null === $tokenField
            || !array_key_exists($tokenField, $this->fields)
        ) {
            return false;
        }
        if (array_key_exists('value', $this->fields[$tokenField])
            && $this->crfs === $this->fields[$tokenField]['value']
        ) {
            return true;
        }
        return false;
    }

}