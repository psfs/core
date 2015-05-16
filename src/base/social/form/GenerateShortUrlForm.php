<?php

namespace PSFS\base\social\form;

use PSFS\base\Router;
use PSFS\base\types\Form;

/**
 * Class GenerateShortUrlForm
 * @package PSFS\base\social\form
 */
class GenerateShortUrlForm extends Form
{

    /**
     * Constructor por defecto
     * @throws \PSFS\base\exception\RouterException
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-social-gus-generate'));
        $this->add("url", array(
            "label" => _("Url Larga"),
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
        return "Test de Url Acortada";
    }
}