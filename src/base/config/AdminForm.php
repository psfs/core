<?php

namespace PSFS\base\config;

use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\Form;

/**
 * Class AdminForm
 * @package PSFS\base\config
 */
class AdminForm extends Form {

    /**
     * @throws \PSFS\base\exception\RouterException
     */
    public function __construct() {
        $this->setAction(Router::getInstance()->getRoute('admin-setup'));
        $this->add('username', array(
            'label' => _('Alias de usuario'),
            'autocomplete' => 'off',
        ))->add('password', array(
            'type' => 'password',
            'label' => _('Contraseña'),
            'autocomplete' => 'off',
        ))->add('profile', array(
            'type' => 'select',
            'label' => _("Perfil"),
            'value' => sha1('superadmin'),
            'autocomplete' => 'off',
            'data' => Security::getProfiles(),
        ));
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            "class" => "col-md-6",
            "autocomplete" => "off",
        ));
        //Añadimos las acciones del formulario
        $this->addButton('submit');
    }

    /**
     * Método que devuelve el título del formulario
     * @return string
     */
    public function getTitle()
    {
        return _("Gestión de Usuarios Administradores");
    }

    /**
     * Método que devuelve el nombre del formulario
     * @return string
     */
    public function getName()
    {
        return "admin_setup";
    }

}
