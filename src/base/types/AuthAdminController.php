<?php
namespace PSFS\base\types;

use PSFS\base\Router;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\base\types\traits\SecureTrait;
use PSFS\controller\base\Admin;

/**
 * Class AuthAdminController
 * @package PSFS\base\types
 */
class AuthAdminController extends Controller
{
    use SecureTrait;

    public function init(): void
    {
        if (!$this->isAdmin()) {
            Admin::staticAdminLogon();
        }
        parent::init();
    }

    protected function getMenu(): array
    {
        return AdminHelper::getAdminRoutes(Router::getInstance()->getRoutes());
    }

}