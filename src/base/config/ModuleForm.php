<?php

namespace PSFS\base\config;

use PSFS\base\types\Form;

/**
 * Class ModuleForm
 * @package PSFS\base\config
 */
class ModuleForm extends Form
{

    /**
     * @Injectable
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
            "Normal" => t("Normal"),
            "Auth" => t("Requiere autenticación de usuario"),
            "AuthAdmin" => t("Requiere autenticación de administrador"),
        );
        $this->add('module', array(
            'label' => t('Nombre del Módulo'),
        ))->add('controllerType', array(
            'label' => t('Tipo de controlador'),
            'type' => 'select',
            'data' => $controllerTypes,
            'required' => false
        ))->add('api', array(
            'label' => t('Clase personalizada para API'),
            'required' => false,
            'placeholder' => t('Namespace de la clase completo'),
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
        return t('Gestión de Módulos');
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
