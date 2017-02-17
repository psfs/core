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
        $this->setAction($route);
        //Añadimos los campos obligatorios
        foreach ($required as $field) {
            $type = (in_array($field, Config::$encrypted)) ? "password" : "text";
            $value = (isset(Config::$defaults[$field])) ? Config::$defaults[$field] : null;
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
                "required" => true,
                "type" => $type,
                "value" => $value,
            ));
        }
        $this->add(Form::SEPARATOR);
        if (!empty($optional) && !empty($data)) {
            foreach ($optional as $field) {
                if (array_key_exists($field, $data) && strlen($data[$field]) > 0) {
                    $this->add($field, array(
                        "label" => _($field),
                        "class" => "col-md-6",
                        "required" => false,
                        "value" => $data[$field],
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
                    $this->add($extraKeys[$key], array(
                        "label" => $field,
                        "class" => "col-md-6",
                        "required" => false,
                        "value" => $data[$field],
                    ));
                }
            }
        }
        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            "class" => "form-horizontal",
        ));
        //Hidratamos el formulario
        $this->setData($data);
        //Añadimos las acciones del formulario
        $this->addButton('submit', _("Guardar configuración"), "submit", array(
            "class" => "btn-success col-md-offset-2"
        ))
            ->addButton('add_field', _('Añadir nuevo parámetro'), 'button', array(
                "onclick" => "javascript:addNewField(document.getElementById('" . $this->getName() . "'));",
                "class" => "btn-warning",
            ));
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName()
    {
        return "config";
    }

    /**
     * Nombre del título del formulario
     * @return string
     */
    public function getTitle()
    {
        return _("Parámetros necesarios para la ejecución de PSFS");
    }
}
