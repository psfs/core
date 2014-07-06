<?php

namespace PSFS\social\form;

use PSFS\base\Router;
use PSFS\types\Form;

class GoogleUrlShortenerForm extends Form
{

    /**
     * Constructor por defecto
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-social-gus'));
        $this->add("api_key", array(
            "label" => _("Api Key"),
            "class" => "col-md-3",
        ));

        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            "class" => "col-md-6",
        ));
        //Añadimos las acciones del formulario
        $this->addButton('submit');
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName()
    {
        return "gus";
    }

    /**
     * Nombre del título del formulario
     * @return string
     */
    public function getTitle()
    {
        return "Google Url Shortener";
    }
}