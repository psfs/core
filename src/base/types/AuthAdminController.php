<?php
    namespace PSFS\base\types;

    use PSFS\controller\base\Admin;

    /**
     * Class AuthAdminController
     * @package PSFS\base\types
     */
    class AuthAdminController extends AuthController {
        public function init() {
            if (!$this->isAdmin()) {
                Admin::staticAdminLogon();
            }
        }
    }