<?php

namespace PSFS\config;
use PSFS\types\Form;

class ConfigForm extends Form{

    /**
     * Constructor por defecto
     */
    function __construct()
    {
        $this->setAction('/config/');
        //Añadimos los campos obligatorios
        foreach(\PSFS\config\Config::$required as $field)
        {
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
            ));
        }
        $this->add(Form::SEPARATOR);
        iF(!empty(\PSFS\config\Config::$optional)) foreach(\PSFS\config\Config::$optional as $field)
        {
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
                "required" => false,
                "pattern" => Form::VALID_ALPHANUMERIC,
            ));
        }
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
           "class" => "col-md-6",
        ));
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName(){
        return "config";
    }

    public function getTitle()
    {
        return "Parámetros necesarios para la ejecución de PSFS";
    }
}