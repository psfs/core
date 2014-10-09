<?php

    namespace PSFS\base\config;

    use PSFS\base\types\Form;
    use PSFS\base\config\Config;
    use PSFS\base\Router;
    use PSFS\base\Security;

    /**
     * Class ModuleForm
     * @package PSFS\base\config
     */
    class ModuleForm extends Form{

        /**
         * @return $this
         * @throws \PSFS\base\exception\FormException
         * @throws \PSFS\base\exception\RouterException
         */
        function __construct()
        {
            $this->setAction(Router::getInstance()->getRoute('admin-module'));
            $this->add('module', array(
                'label' => _('Nombre del Módulo'),
            ));
            $data = Security::getInstance()->getAdmins();
            //Aplicamos estilo al formulario
            $this->setAttrs(array(
                "class" => "col-md-6",
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
            return _("Gestión de Módulos");
        }

        /**
         * Método que devuelve el nombre del formulario
         * @return string
         */
        public function getName()
        {
            return "admin_modules";
        }

    }