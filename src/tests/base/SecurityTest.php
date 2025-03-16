<?php

namespace PSFS\tests\base;

use Exception;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\services\AdminServices;

/**
 * Class SecurityTest
 * @package PSFS\tests\base
 * @runInSeparateProcess
 */
class SecurityTest extends TestCase
{
    /**
     * Test to check if the Logger has been created successful
     * @return Security
     */
    public function getInstance(): Security
    {
        global $_SESSION;
        if (null === $_SESSION) {
            $_SESSION = [];
        }
        $instance = Security::getInstance(true);
        Security::setTest(false);

        $this->assertNotNull($instance, 'Security instance is null');
        $this->assertInstanceOf(Security::class, $instance, 'Instance is different than expected');
        return $instance;
    }

    /**
     * Test basic static functionality for Security class
     */
    public function testSecurityBasics(): Security
    {
        $security = $this->getInstance();
        $this->assertInstanceOf(Security::class, $security);

        $profiles = $security->getAdminProfiles();
        $this->assertArrayHasKey(AuthHelper::ADMIN_ID_TOKEN, $profiles, 'Malformed array');
        $this->assertArrayHasKey(AuthHelper::MANAGER_ID_TOKEN, $profiles, 'Malformed array');
        $this->assertArrayHasKey(AuthHelper::USER_ID_TOKEN, $profiles, 'Malformed array');

        $cleanProfiles = $security->getAdminCleanProfiles();
        $this->assertNotEmpty($cleanProfiles, 'Malformed security profiles array');
        $this->assertTrue(in_array(AuthHelper::ADMIN_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        $this->assertTrue(in_array(AuthHelper::MANAGER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        $this->assertTrue(in_array(AuthHelper::USER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        return $security;
    }

    /**
     * @return Security
     * @throws GeneratorException
     */
    #[Depends('testSecurityBasics')]
    public function testSecurityUserManagement(): Security
    {
        $user = [
            'username' => uniqid('test', true),
            'password' => uniqid('test', true),
            'profile' => AuthHelper::ADMIN_ID_TOKEN,
        ];
        $security = $this->getInstance();
        $security->saveUser($user);

        $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', 'Error trying to save admins');
        $this->assertNull($security->getUser());
        $this->assertNull($security->getAdmin());
        $this->assertTrue($security->canDo('something'));
        $this->assertFalse($security->isLogged());
        $this->assertFalse($security->isAdmin());
        $this->assertFalse($security->isManager());
        $this->assertFalse($security->isUser());
        $this->assertFalse($security->isSuperAdmin());

        $security->updateUser($user);
        $this->assertNotNull($security->getUser(), 'An error occurred when update user in session');
        $this->assertFalse($security->checkAdmin(uniqid('test', true), uniqid('error', true), true), 'Error checking admin user');
        $this->assertNull($security->getAdmin(), 'Wrong admin parser');

        $_COOKIE[AuthHelper::generateProfileHash()] = AuthHelper::encrypt($user['username'] . ':' . $user['password'], AuthHelper::ADMIN_ID_TOKEN);
        Request::getInstance()->init();
        $this->assertTrue($security->checkAdmin(null, null, true), 'An error occurred verifying the admin user');
        AdminServices::setTest(true);
        $admins = AdminServices::getInstance()->getAdmins();
        $this->assertArrayHasKey($user['username'], $admins, 'Admin is not into credentials file');
        $this->assertEquals($user['profile'], $admins[$user['username']]['profile'], 'Admin user with different profile');
        $admin = $security->getAdmin();
        $this->assertNotNull($admin, 'An error ocurred gathering the admin user');
        $this->assertEquals($admin['alias'], $user['username'], 'Wrong data gathered from admins.json');
        $this->assertEquals($admin['profile'], $user['profile'], 'Wrong profile gathered from admins.json');
        $this->assertTrue($security->isSuperAdmin(), 'Wrong checking for super admin profile');
        $this->assertTrue($security->isLogged());
        $this->assertTrue($security->isAdmin());
        $this->assertFalse($security->isManager());
        $this->assertFalse($security->isUser());

        $security->updateSession(true);
        $this->assertNotEmpty($security->getSessionKey(AuthHelper::ADMIN_ID_TOKEN), 'Error saving sessions');

        $security->deleteUser($user['username']);
        $this->assertTrue($security->checkAdmin($user['username'], $user['password'], true), 'Error checking admin user');

        return $security;

    }

    /**
     * @param Security $security
     * @throws Exception
     */
    #[Depends('testSecurityUserManagement')]
    public function testSessionHandler(Security $security)
    {

        $testValue = random_int(0, 1e5);
        $security->setSessionKey('test', $testValue);
        $this->assertNotNull($security->getSessionKey('test'), 'Error trying to gather the session key');
        $this->assertEquals($security->getSessionKey('test'), $testValue, 'The session key value is not the same than expected');

        $flashValue = 'test value for flash';
        $security->setFlash('flash_test', $flashValue);
        $security->updateSession();
        $this->assertNotEmpty($security->getFlashes(), 'Flash key not saved');
        $gatherData = $security->getFlash('flash_test');
        $this->assertNotNull($gatherData, 'Error trying to gather the flash key');
        $this->assertEquals($flashValue, $gatherData, 'Error gathering the flash data, there is not the same data than expected');
        $security->clearFlashes();
        $this->assertNull($security->getFlash('flash_test'), 'Flash key not deleted');
        $this->assertEmpty($security->getFlashes(), 'Flash with data yet');
        $security->closeSession();
    }

    /**
     * @throws Exception
     */
    public function testCheckNotAuthorizedRoute(): void
    {
        Template::setTest(true);
        $this->assertNotEmpty($this->getInstance()->notAuthorized('test'), 'Fail to render the redirection template');
        Template::setTest(false);
    }

}
