<?php

namespace PSFS\base\config;
use PSFS\base\Router;
use PSFS\base\types\Form;

/**
 * Class LoginForm
 * @package PSFS\base\config
 */
class LoginForm extends Form{

    /**
     * Constructor por defecto
     * @throws \PSFS\base\exception\RouterException
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-login'));
        $this->add("user", array(
            "label" => _("Usuario"),
            "required" => true,
            "pattern" => Form::VALID_ALPHANUMERIC,
            "ng-model" => "username",
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
        ->addButton('submit', _("Acceder como {{username}}"))
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