<?php
namespace PSFS\test\base;
use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\services\AdminServices;

/**
 * Class SecurityTest
 * @package PSFS\test\base
 * @runInSeparateProcess
 */
class SecurityTest extends TestCase
{
    /**
     * Test to check if the Logger has been created successful
     * @return Security
     */
    public function getInstance()
    {
        global $_SESSION;
        if(null === $_SESSION) {
            $_SESSION = [];
        }
        $instance = Security::getInstance(true);
        Security::setTest(false);

        self::assertNotNull($instance, 'Security instance is null');
        self::assertInstanceOf(Security::class, $instance, 'Instance is different than expected');
        return $instance;
    }

    /**
     * Test basic static functionality for Security class
     */
    public function testSecurityBasics(): Security
    {
        $security = $this->getInstance();

        $profiles = $security->getAdminProfiles();
        self::assertArrayHasKey(Security::ADMIN_ID_TOKEN, $profiles, 'Malformed array');
        self::assertArrayHasKey(Security::MANAGER_ID_TOKEN, $profiles, 'Malformed array');
        self::assertArrayHasKey(Security::USER_ID_TOKEN, $profiles, 'Malformed array');

        $cleanProfiles = $security->getAdminCleanProfiles();
        self::assertNotEmpty($cleanProfiles, 'Malformed security profiles array');
        self::assertTrue(in_array(Security::ADMIN_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        self::assertTrue(in_array(Security::MANAGER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        self::assertTrue(in_array(Security::USER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        return $security;
    }

    /**
     * @param Security $security
     * @depends testSecurityBasics
     * @return Security
     */
    public function testSecurityUserManagement(Security $security): Security
    {
        $user = [
            'username' => uniqid('test', true),
            'password' => uniqid('test', true),
            'profile' => Security::ADMIN_ID_TOKEN,
        ];
        $security = $this->getInstance();
        $security->saveUser($user);

        self::assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', 'Error trying to save admins');
        self::assertNull($security->getUser());
        self::assertNull($security->getAdmin());
        self::assertTrue($security->canDo('something'));
        self::assertFalse($security->isLogged());
        self::assertFalse($security->isAdmin());

        $security->updateUser($user);
        self::assertNotNull($security->getUser(), 'An error occurred when update user in session');
        self::assertFalse($security->checkAdmin(uniqid('test', true), uniqid('error', true), true), 'Error checking admin user');
        self::assertNull($security->getAdmin(), 'Wrong admin parser');

        $_COOKIE[substr(Security::MANAGER_ID_TOKEN, 0, 8)] = base64_encode($user['username'] . ':' . $user['password']);
        Request::getInstance()->init();
        self::assertTrue($security->checkAdmin(null, null, true), 'An error occurred verifying the admin user');
        AdminServices::setTest(true);
        $admins = AdminServices::getInstance()->getAdmins();
        self::assertArrayHasKey($user['username'], $admins, 'Admin is not into credentials file');
        self::assertEquals($user['profile'], $admins[$user['username']]['profile'], 'Admin user with different profile');
        $admin = $security->getAdmin();
        self::assertNotNull($admin, 'An error ocurred gathering the admin user');
        self::assertEquals($admin['alias'], $user['username'], 'Wrong data gathered from admins.json');
        self::assertEquals($admin['profile'], $user['profile'], 'Wrong profile gathered from admins.json');
        self::assertTrue($security->isSuperAdmin(), 'Wrong checking for super admin profile');
        self::assertTrue($security->isLogged());
        self::assertTrue($security->isAdmin());

        $security->updateSession(true);
        self::assertNotEmpty($security->getSessionKey(Security::ADMIN_ID_TOKEN), 'Error saving sessions');
        return $security;

    }

    /**
     * @param Security $security
     * @depends testSecurityUserManagement
     * @throws \Exception
     */
    public function testSessionHandler(Security $security) {

        $testValue = random_int(0, 1e5);
        $security->setSessionKey('test', $testValue);
        self::assertNotNull($security->getSessionKey('test'), 'Error trying to gather the session key');
        self::assertEquals($security->getSessionKey('test'), $testValue, 'The session key value is not the same than expected');

        $flashValue = 'test value for flash';
        $security->setFlash('flash_test', $flashValue);
        $security->updateSession();
        self::assertNotEmpty($security->getFlashes(), 'Flash key not saved');
        $gatherData = $security->getFlash('flash_test');
        self::assertNotNull($gatherData, 'Error trying to gather the flash key');
        self::assertEquals($flashValue, $gatherData, 'Error gathering the flash data, there is not the same data than expected');
        $security->clearFlashes();
        self::assertNull($security->getFlash('flash_test'), 'Flash key not deleted');
        self::assertEmpty($security->getFlashes(), 'Flash with data yet');
        $security->closeSession();
        //self::assertNotEquals($sessionId, session_id(), 'An error occurred trying to close the session');
    }

}