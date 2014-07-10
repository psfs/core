<?php

namespace PSFS\config;
use PSFS\base\Router;
use PSFS\types\Form;

class LoginForm extends Form{

    /**
     * Constructor por defecto
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-login'));
        $this->add("user", array(
            "label" => _("Usuario"),
            "required" => true,
            "pattern" => Form::VALID_ALPHANUMERIC,
        ))
        ->add("pass", array(
            "label" => _("Contraseña"),
            "required" => true,
            "type" => "password",
        ))
        ->add(Form::SEPARATOR)
        ->add("route", array(
            "required" => false,
            "type" => "hidden",
        ))
        ->addButton('submit', _("Acceso"))
        ->addButton("cancel", _("Cancelar"), "button", array(
            "onclick" => "javacript:location.href = \"" . Router::getInstance()->getRoute('') . "\";",
            "class" => "btn-link",
        ));
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName(){
        return "login";
    }
}