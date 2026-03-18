<?php

namespace PSFS\base\config;

use PSFS\base\types\Form;

/**
 * @package PSFS\base\config
 */
class ModuleForm extends Form
{

    /**
     * @Injectable
     * @var \PSFS\base\Router
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
            "Auth" => t("Requires user authentication"),
            "AuthAdmin" => t("Requires administrator authentication"),
        );
        $this->add('module', array(
            'label' => t('Module Name'),
        ))->add('controllerType', array(
            'label' => t('Controller type'),
            'type' => 'select',
            'data' => $controllerTypes,
            'required' => false
        ))->add('api', array(
            'label' => t('Custom API class'),
            'required' => false,
            'placeholder' => t('Full class namespace'),
        ));
        // Apply form style.
        $this->setAttrs(array(
            'class' => 'col-md-6',
        ));
        $this->addButton('submit', 'Generate module');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return t('Module Management');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'admin_modules';
    }

}
