<?php

namespace PSFS\base\config;

use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\Form;

/**
 * @package PSFS\base\config
 */
class AdminForm extends Form
{

    /**
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function __construct()
    {
        parent::__construct();
        $this->setAction(Router::getInstance()->getRoute('admin-setup'));
        $this->add('username', array(
            'label' => t('User Alias'),
            'autocomplete' => 'off',
        ))->add('password', array(
            'type' => 'password',
            'label' => t('Password'),
            'autocomplete' => 'off',
        ))->add('profile', array(
            'type' => 'select',
            'label' => t("Role"),
            'value' => sha1('superadmin'),
            'autocomplete' => 'off',
            'data' => Security::getProfiles(),
        ));
        //Apply styling to the form
        $this->setAttrs(array(
            "class" => "col-md-6",
            "autocomplete" => "off",
        ));
        //Add action buttons to form
        $this->addButton('submit');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return t("Admin user control panel");
    }

    /**
     * @return string
     */
    public function getName()
    {
        return "admin_setup";
    }

}
