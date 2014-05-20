<?php

namespace PSFS\config;
use PSFS\base\Router;
use PSFS\types\Form;

class ConfigForm extends Form{

    /**
     * Constructor por defecto
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-config'));
        //Añadimos los campos obligatorios
        foreach(Config::$required as $field)
        {
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
            ));
        }
        $this->add(Form::SEPARATOR);
        iF(!empty(Config::$optional)) foreach(Config::$optional as $field)
        {
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
                "required" => false,
                "pattern" => Form::VALID_ALPHANUMERIC,
            ));
        }
        $data = Config::getInstance()->dumpConfig();
        $extra = array();
        if(!empty($data)) $extra = array_diff($data, array_merge(Config::$required, Config::$optional));
        if(!empty($extra)) foreach($extra as $key => $field)
        {
            $this->add($key, array(
                "label" => _($key),
                "class" => "col-md-6",
                "required" => false,
                "pattern" => Form::VALID_ALPHANUMERIC,
            ));
        }
        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
           "class" => "col-md-6",
        ));
        //Hidratamos el formulario
        $this->setData($data);
        //Añadimos las acciones del formulario
        $this->addButton('submit')
            ->addButton('add_field', _('Añadir nuevo parámetro'), 'button', array(
               "onclick" => "javascript:addNewField(document.getElementById('". $this->getName() ."'));",
               "class" => "btn-success",
            ));
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName(){
        return "config";
    }

    /**
     * Nombre del título del formulario
     * @return string
     */
    public function getTitle()
    {
        return "Parámetros necesarios para la ejecución de PSFS";
    }
}