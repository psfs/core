<?php

namespace PSFS\base\config;

use PSFS\base\Security;
use PSFS\base\types\Form;

/**
 * Class ModuleForm
 * @package PSFS\base\config
 */
class ModuleForm extends Form
{

    /**
     * @Inyectable
     * @var \PSFS\base\Router $router
     */
    protected $router;

    /**
     * @throws \PSFS\base\exception\FormException
     * @throws \PSFS\base\exception\RouterException
     */
    public function __construct()
    {
        parent::__construct();
        $this->init();
        $this->setAction($this->router->getRoute('admin-module'))
            ->setAttrs(array());

        $controllerTypes = array(
            "" => _("Normal"),
            "Auth" => _("Requiere autenticación de usuario"),
            "AuthAdmin" => _("Requiere autenticación de administrador"),
        );
        if(Config::getParam('psfs.auth')) {
            $controllerTypes['SessionAuthApi'] = _('Requiere autenticación usando PSFS AUTH');
        }
        $this->add('module', array(
            'label' => _('Nombre del Módulo'),
        ))->add('controllerType', array(
            'label' => _('Tipo de controlador'),
            'type' => 'select',
            'data' => $controllerTypes,
            'required' => false
        ))->add('api', array(
            'label' => _('Clase personalizada para API'),
            'required' => false,
            'placeholder' => _('Namespace de la clase completo'),
        ));
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            'class' => 'col-md-6',
        ));
        $this->addButton('submit', 'Generar módulo');
    }

    /**
     * Método que devuelve el título del formulario
     * @return string
     */
    public function getTitle()
    {
        return _('Gestión de Módulos');
    }

    /**
     * Método que devuelve el nombre del formulario
     * @return string
     */
    public function getName()
    {
        return 'admin_modules';
    }

}
