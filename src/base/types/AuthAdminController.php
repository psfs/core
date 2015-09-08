<?php
    namespace PSFS\base\types;

    use PSFS\controller\Admin;

    /**
     * Class AuthAdminController
     * @package PSFS\base\types
     */
    class AuthAdminController extends AuthController {
        public function __construct() {
            $this->init();
            if(!$this->isAdmin()) {
                Admin::staticAdminLogon();
            }
        }
    }