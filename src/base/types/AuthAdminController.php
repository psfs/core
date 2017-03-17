<?php
namespace PSFS\base\types;

use PSFS\base\Router;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\base\types\interfaces\AuthInterface;
use PSFS\base\types\traits\SecureTrait;
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

    protected function getMenu()
    {
        return AdminHelper::getAdminRoutes(Router::getInstance()->getRoutes());
    }
}