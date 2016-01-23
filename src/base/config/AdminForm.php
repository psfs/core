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
            'label' => _('User Alias'),
            'autocomplete' => 'off',
        ))->add('password', array(
            'type' => 'password',
            'label' => _('Password'),
            'autocomplete' => 'off',
        ))->add('profile', array(
            'type' => 'select',
            'label' => _("Role"),
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
     * Method that returns the form's title
     * @return string
     */
    public function getTitle()
    {
        return _("Admin user control panel");
    }

    /**
     * Method that returns the form's name
     * @return string
     */
    public function getName()
    {
        return "admin_setup";
    }

}
