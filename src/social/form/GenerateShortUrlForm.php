<?php

namespace PSFS\social\form;

use PSFS\base\Router;
use PSFS\types\Form;

class GenerateShortUrlForm extends Form
{

    /**
     * Constructor por defecto
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-social-gus-generate'));
        $this->add("url", array(
            "label" => _("Url Larga"),
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
        return "Test de Url Acortada";
    }
}