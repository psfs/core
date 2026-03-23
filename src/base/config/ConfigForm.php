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
        $this->addRequiredFields($required);
        $this->add(Form::SEPARATOR);
        $this->addOptionalFields($optional, $data);
        $this->addExtraFields($required, $optional, $data);
        $this->add(Form::SEPARATOR);
        $this->setAttrs(['class' => 'form-horizontal']);
        $this->setData($data);
        $add = $this->buildAddFieldButtonAttrs();
        $this->addButton('submit', t('Save configuration'), 'submit', array(
            'class' => 'btn-success col-md-offset-2 md-primary',
            'icon' => 'fa-save',
        ))
            ->addButton('add_field', t('Add new parameter'), 'button', $add);
    }

    private function addRequiredFields(array $required): void
    {
        foreach ($required as $field) {
            $type = in_array($field, Config::$encrypted, true) ? 'password' : 'text';
            $value = isset(Config::$defaults[$field]) ? Config::$defaults[$field] : null;
            $this->add($field, [
                'label' => t($field),
                'class' => 'col-md-6',
                'required' => true,
                'type' => $type,
                'value' => $value,
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
                'value' => $data[$field],
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
                'value' => $data[$field],
                'type' => $this->resolveFieldType($field),
            ]);
        }
    }

    private function resolveFieldType(string $field): string
    {
        return preg_match('/(password|secret)/i', $field) ? 'password' : 'text';
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
