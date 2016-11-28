<?php
namespace PSFS\base\types;

use PSFS\base\types\interfaces\AuthInterface;
use PSFS\controller\base\Admin;

/**
 * Class AuthAdminController
 * @package PSFS\base\types
 */
class AuthAdminController extends Controller implements AuthInterface
{
    use SecureTrait;

    public function init()
    {
        parent::init();
        if (!$this->isAdmin()) {
            Admin::staticAdminLogon();
        }
    }
}