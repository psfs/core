<?php

    namespace PSFS\base\config;

    use PSFS\base\Security;
    use PSFS\base\types\Form;

    /**
     * Class ModuleForm
     * @package PSFS\base\config
     */
    class ModuleForm extends Form {

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
            $this->init();
            $this->setAction($this->router->getRoute('admin-module'))
                ->setAttrs(array(

                ));
            $this->add('module', array(
                'label' => _('Nombre del Módulo'),
            ))
            ->add('force', array(
                'label' => _('Sobreescribir ficheros'),
                'type' => 'select',
                'data' => array(_('No'), _('Sí')),
                'required' => false
            ))
            ->add('is_module', array(
                'label' => _('Es un módulo de PSFS'),
                'type' => 'select',
                'data' => array(_('No'), _('Sí')),
                'required' => false
            ))
            ->add('controllerType', array(
                'label' => _('Tipo de controlador'),
                'type' => 'select',
                'data' => array(
                    "" => _("Normal"),
                    "Auth" => _("Requiere autenticación de usuario"),
                    "AuthAdmin" => _("Requiere autenticación de administrador"),
                ),
                'required' => false
            ));
            $data = Security::getInstance()->getAdmins();
            //Aplicamos estilo al formulario
            $this->setAttrs(array(
                'class' => 'col-md-6',
            ));
            //Hidratamos el formulario
            $this->setData($data);
            //Añadimos las acciones del formulario
            $this->addButton('submit', 'Generar');
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
