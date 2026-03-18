<?php

namespace PSFS\tests\base\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\Security;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\controller\ConfigController;
use PSFS\controller\UserController;
use PSFS\base\exception\ApiException;

class AdminAccessControlTest extends TestCase
{
    private string $adminsPath;
    private bool $adminsExisted = false;
    private string $adminsBackup = '';

    protected function setUp(): void
    {
        $this->adminsPath = CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json';
        Security::setTest(false);
        Security::dropInstance();
        if (file_exists($this->adminsPath)) {
            $this->adminsExisted = true;
            $this->adminsBackup = (string)file_get_contents($this->adminsPath);
        }
    }

    protected function tearDown(): void
    {
        Security::dropInstance();
        if ($this->adminsExisted) {
            file_put_contents($this->adminsPath, $this->adminsBackup);
        } else {
            @unlink($this->adminsPath);
        }
    }

    public function testManagerCannotWriteAdminUsers(): void
    {
        $this->seedAdmins([
            'manager' => ['hash' => sha1('manager:pass'), 'profile' => AuthHelper::MANAGER_ID_TOKEN],
        ]);
        Security::getInstance()->updateAdmin('manager', AuthHelper::MANAGER_ID_TOKEN);

        $method = new \ReflectionMethod(UserController::class, 'assertSuperAdminUserWriteAccess');
        $method->setAccessible(true);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(403);
        $method->invoke(null);
    }

    public function testManagerCannotWriteConfig(): void
    {
        $this->seedAdmins([
            'manager' => ['hash' => sha1('manager:pass'), 'profile' => AuthHelper::MANAGER_ID_TOKEN],
        ]);
        Security::getInstance()->updateAdmin('manager', AuthHelper::MANAGER_ID_TOKEN);

        $method = new \ReflectionMethod(ConfigController::class, 'assertSuperAdminConfigWriteAccess');
        $method->setAccessible(true);

        $this->expectException(ConfigException::class);
        $method->invoke(null);
    }

    public function testSuperAdminCanWriteAdminUsersAndConfig(): void
    {
        $this->seedAdmins([
            'admin' => ['hash' => sha1('admin:pass'), 'profile' => AuthHelper::ADMIN_ID_TOKEN],
        ]);
        Security::getInstance()->updateAdmin('admin', AuthHelper::ADMIN_ID_TOKEN);

        $userMethod = new \ReflectionMethod(UserController::class, 'assertSuperAdminUserWriteAccess');
        $userMethod->setAccessible(true);
        $userMethod->invoke(null);
        $this->assertTrue(true);

        $configMethod = new \ReflectionMethod(ConfigController::class, 'assertSuperAdminConfigWriteAccess');
        $configMethod->setAccessible(true);
        $configMethod->invoke(null);
        $this->assertTrue(true);
    }

    public function testBootstrapWithoutAdminsAllowsWriteActions(): void
    {
        @unlink($this->adminsPath);
        Security::getInstance()->updateAdmin('manager', AuthHelper::MANAGER_ID_TOKEN);

        $userMethod = new \ReflectionMethod(UserController::class, 'assertSuperAdminUserWriteAccess');
        $userMethod->setAccessible(true);
        $userMethod->invoke(null);
        $this->assertTrue(true);

        $configMethod = new \ReflectionMethod(ConfigController::class, 'assertSuperAdminConfigWriteAccess');
        $configMethod->setAccessible(true);
        $configMethod->invoke(null);
        $this->assertTrue(true);
    }

    private function seedAdmins(array $admins): void
    {
        Cache::getInstance()->storeData($this->adminsPath, $admins, Cache::JSONGZ, true);
    }
}
