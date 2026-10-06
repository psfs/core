<?php

namespace PSFS\base\config;

use PSFS\base\types\Form;

/**
 * @package PSFS\base\config
 */
class ConfigForm extends Form
{

    /**
     * @param string $route
     * @param array $required
     * @param array $optional
     * @param array $data
     * @throws \PSFS\base\exception\FormException
     * @throws \PSFS\base\exception\RouterException
     */
    public function __construct($route, array $required, array $optional = [], array $data = [])
    {
        parent::__construct();
        $this->setAction($route);
        $this->addRequiredFields($required, $data);
        $this->add(Form::SEPARATOR);
        $this->addOptionalFields($optional, $data);
        $this->addExtraFields($required, $optional, $data);
        $this->add(Form::SEPARATOR);
        $this->setAttrs(['class' => 'form-horizontal']);
        $this->setData($this->maskSensitiveValues($data));
        $add = $this->buildAddFieldButtonAttrs();
        $this->addButton('submit', t('Save configuration'), 'submit', array(
            'class' => 'btn-success col-md-offset-2 md-primary',
            'icon' => 'fa-save',
        ))
            ->addButton('add_field', t('Add new parameter'), 'button', $add);
    }

    private function addRequiredFields(array $required, array $data): void
    {
        foreach ($required as $field) {
            $type = in_array($field, Config::$encrypted, true) || $this->isSensitiveField($field) ? 'password' : 'text';
            $value = isset(Config::$defaults[$field]) ? Config::$defaults[$field] : null;
            $hasExistingSensitiveValue = $this->isSensitiveField($field) && $this->hasNonEmptyFieldValue($data, $field);
            $this->add($field, [
                'label' => t($field),
                'class' => 'col-md-6',
                'required' => !$hasExistingSensitiveValue,
                'type' => $type,
                'value' => $this->isSensitiveField($field) ? '' : $value,
            ]);
        }
    }

    private function addOptionalFields(array $optional, array $data): void
    {
        if (empty($optional) || empty($data)) {
            return;
        }
        foreach ($optional as $field) {
            if (!$this->hasNonEmptyFieldValue($data, $field)) {
                continue;
            }
            $this->add($field, [
                'label' => t($field),
                'class' => 'col-md-6',
                'required' => false,
                'value' => $this->isSensitiveField($field) ? '' : $data[$field],
                'type' => $this->resolveFieldType($field),
            ]);
        }
    }

    private function addExtraFields(array $required, array $optional, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $extraKeys = array_diff(array_keys($data), array_merge($required, $optional));
        foreach ($extraKeys as $field) {
            if (!$this->hasNonEmptyFieldValue($data, $field)) {
                continue;
            }
            $this->add($field, [
                'label' => $field,
                'class' => 'col-md-6',
                'required' => false,
                'value' => $this->isSensitiveField($field) ? '' : $data[$field],
                'type' => $this->resolveFieldType($field),
            ]);
        }
    }

    private function resolveFieldType(string $field): string
    {
        return $this->isSensitiveField($field) ? 'password' : 'text';
    }

    private function isSensitiveField(string $field): bool
    {
        return preg_match('/(?:secret|password|token|hash)/i', $field) === 1;
    }

    private function maskSensitiveValues(array $data): array
    {
        foreach ($data as $field => $value) {
            if (is_string($field) && $this->isSensitiveField($field)) {
                $data[$field] = '';
            }
        }

        return $data;
    }

    public function maskSensitiveFieldValues(): void
    {
        foreach ($this->fields as $field => &$definition) {
            if (is_string($field) && is_array($definition) && $this->isSensitiveField($field)) {
                $definition['value'] = '';
            }
        }
    }

    public function retainExistingSensitiveValues(array $existing): void
    {
        foreach ($this->fields as $field => &$definition) {
            if (!is_string($field) || !is_array($definition) || !$this->isSensitiveField($field)) {
                continue;
            }

            $value = $definition['value'] ?? null;
            if (($value === null || $value === '') && array_key_exists($field, $existing)) {
                $definition['value'] = $existing[$field];
            }
        }
    }

    private function hasNonEmptyFieldValue(array $data, string $field): bool
    {
        return array_key_exists($field, $data) && strlen((string)($data[$field] ?? '')) > 0;
    }

    private function buildAddFieldButtonAttrs(): array
    {
        return [
            'class' => 'btn-warning md-default',
            'icon' => 'fa-plus',
            'onclick' => 'javascript:addNewField(document.getElementById("' . $this->getName() . '"));',
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'config';
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return t('Required parameters to run PSFS');
    }
}
