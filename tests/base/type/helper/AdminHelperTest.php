<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\types\helpers\AdminHelper;

final class AdminHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Security::dropInstance();
    }

    public function testGetAdminRoutesSkipsCanonicalManagerItemRoutesButKeepsOtherHiddenAdminRoutes(): void
    {
        $routes = AdminHelper::getAdminRoutes([
            [
                'http' => 'GET',
                'default' => '/admin/demo/users',
                'slug' => 'admin-demo-users',
                'label' => 'Users Manager',
                'icon' => 'fa-database',
                'module' => 'DEMO',
                'visible' => true,
            ],
            [
                'http' => 'GET',
                'default' => '/admin/demo/users/{id}',
                'slug' => 'admin-demo-users-id',
                'label' => 'Users Manager item',
                'icon' => 'fa-database',
                'module' => 'DEMO',
                'visible' => false,
            ],
            [
                'http' => 'GET',
                'default' => '/admin/switch-user',
                'slug' => 'admin-switch-user',
                'label' => 'Switch user',
                'icon' => 'fa-exchange-alt',
                'module' => 'PSFS',
                'visible' => false,
            ],
        ]);

        $this->assertCount(1, $routes['DEMO']['visible']);
        $this->assertSame('admin-demo-users', $routes['DEMO']['visible'][0]['slug']);
        $this->assertCount(1, $routes['PSFS']['hidden']);
        $this->assertSame('admin-switch-user', $routes['PSFS']['hidden'][0]['slug']);
        $this->assertArrayNotHasKey('hidden', $routes['DEMO']);
    }
}
