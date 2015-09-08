<?php
    namespace PSFS\base\types;

    use PSFS\base\exception\AccessDeniedException;

    /**
     * Class AuthAdminController
     * @package PSFS\base\types
     */
    class AuthAdminController extends AuthController {
        public function __construct() {
            $this->init();
            if(!$this->isAdmin()) {
                throw new AccessDeniedException(_("Restricted zone"));
            }
        }
    }