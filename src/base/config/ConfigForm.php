<?php

namespace PSFS\base\config;

use PSFS\base\types\Form;

/**
 * Class ConfigForm
 * @package PSFS\base\config
 */
class ConfigForm extends Form
{

    /**
     * Constructor por defecto
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
        //Añadimos los campos obligatorios
        foreach ($required as $field) {
            $type = in_array($field, Config::$encrypted) ? 'password' : 'text';
            $value = isset(Config::$defaults[$field]) ? Config::$defaults[$field] : null;
            $this->add($field, array(
                'label' => _($field),
                'class' => 'col-md-6',
                'required' => true,
                'type' => $type,
                'value' => $value,
            ));
        }
        $this->add(Form::SEPARATOR);
        if (!empty($optional) && !empty($data)) {
            foreach ($optional as $field) {
                if (array_key_exists($field, $data) && strlen($data[$field]) > 0) {
                    $type = preg_match('/(password|secret)/i', $field) ? 'password' : 'text';
                    $this->add($field, array(
                        'label' => _($field),
                        'class' => 'col-md-6',
                        'required' => false,
                        'value' => $data[$field],
                        'type' => $type,
                    ));
                }
            }
        }
        $extra = array();
        $extraKeys = array();
        if (!empty($data)) {
            $extraKeys = array_keys($data);
            $extra = array_diff($extraKeys, array_merge($required, $optional));
        }
        if (!empty($extra)) {
            foreach ($extra as $key => $field) {
                if (strlen($data[$field]) > 0) {
                    $type = preg_match('/(password|secret)/i', $field) ? 'password' : 'text';
                    $this->add($extraKeys[$key], array(
                        'label' => $field,
                        'class' => 'col-md-6',
                        'required' => false,
                        'value' => $data[$field],
                        'type' => $type,
                    ));
                }
            }
        }
        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            'class' => 'form-horizontal',
        ));
        //Hidratamos el formulario
        $this->setData($data);
        //Añadimos las acciones del formulario
        $add = [
            'class' => 'btn-warning md-default',
            'icon' => 'fa-plus',
        ];
        if(Config::getParam('admin.version', 'v1') === 'v1') {
            $add['onclick'] = 'javascript:addNewField(document.getElementById("' . $this->getName() . '"));';
        } else {
            $add['ng-click'] = 'addNewField()';
        }
        $this->addButton('submit', _('Guardar configuración'), 'submit', array(
                'class' => 'btn-success col-md-offset-2 md-primary',
                'icon' => 'fa-save',
            ))
            ->addButton('add_field', _('Añadir nuevo parámetro'), 'button', $add);
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName()
    {
        return 'config';
    }

    /**
     * Nombre del título del formulario
     * @return string
     */
    public function getTitle()
    {
        return t('Parámetros necesarios para la ejecución de PSFS');
    }
}
