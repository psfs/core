<?php

namespace PSFS\base\social\form;

use PSFS\base\Router;
use PSFS\base\types\Form;

/**
 * Class GoogleUrlShortenerForm
 * @package PSFS\base\social\form
 */
class GoogleUrlShortenerForm extends Form
{

    /**
     * Constructor por defecto
     * @return $this
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-social-gus'));
        $this->add("api_key", array(
            "label" => _("Api Key"),
            "class" => "col-md-8",
        ));

        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            "class" => "col-md-8",
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