<?php
namespace PSFS\test\base;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;

/**
 * Class SecurityTest
 * @package PSFS\test\base
 * @runInSeparateProcess
 */
class SecurityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test to check if the Logger has been created successful
     * @return Security
     */
    public function getInstance()
    {
        if(null === $_SESSION) {
            $_SESSION = [];
        }
        $instance = Security::getInstance(true);

        $this->assertNotNull($instance, 'Security instance is null');
        $this->assertInstanceOf(Security::class, $instance, 'Instance is different than expected');
        return $instance;
    }

    /**
     * Test basic static functionality for Security class
     */
    public function testSecurityBasics() {
        $security = $this->getInstance();

        $profiles = $security->getAdminProfiles();
        $this->assertArrayHasKey(Security::ADMIN_ID_TOKEN, $profiles, 'Malformed array');
        $this->assertArrayHasKey(Security::MANAGER_ID_TOKEN, $profiles, 'Malformed array');
        $this->assertArrayHasKey(Security::USER_ID_TOKEN, $profiles, 'Malformed array');

        $cleanProfiles = $security->getAdminCleanProfiles();
        $this->assertNotEmpty($cleanProfiles, 'Malformed security profiles array');
        $this->assertTrue(in_array(Security::ADMIN_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        $this->assertTrue(in_array(Security::MANAGER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        $this->assertTrue(in_array(Security::USER_ID_TOKEN, $cleanProfiles, true), 'Key not exists');
        return $security;
    }

    /**
     * @param Security $security
     * @depends testSecurityBasics
     * @return Security
     */
    public function testSecurityUserManagement(Security $security) {
        $user = [
            'username' => uniqid('test', true),
            'password' => uniqid('test', true),
            'profile' => Security::ADMIN_ID_TOKEN,
        ];
        $security = $this->getInstance();
        $security->saveUser($user);

        $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', 'Error trying to save admins');
        $this->assertNull($security->getUser());
        $this->assertNull($security->getAdmin());
        $this->assertTrue($security->canDo('something'));
        $this->assertFalse($security->isLogged());
        $this->assertFalse($security->isAdmin());

        $security->updateUser($user);
        $this->assertNotNull($security->getUser(), 'An error occurred when update user in session');
        $this->assertFalse($security->checkAdmin(uniqid('test', true), uniqid('error', true), true), 'Error checking admin user');
        $this->assertNull($security->getAdmin(), 'Wrong admin parser');

        $_COOKIE[substr(Security::MANAGER_ID_TOKEN, 0, 8)] = base64_encode($user['username'] . ':' . $user['password']);
        Request::getInstance()->init();
        $this->assertTrue($security->checkAdmin(null, null, true), 'An error occurred verifying the admin user');
        $admin = $security->getAdmin();
        $this->assertNotNull($admin, 'An error ocurred gathering the admin user');
        $this->assertEquals($admin['alias'], $user['username'], 'Wrong data gathered from admins.json');
        $this->assertEquals($admin['profile'], $user['profile'], 'Wrong profile gathered from admins.json');
        $this->assertTrue($security->isSuperAdmin(), 'Wrong checking for super admin profile');

        $this->assertTrue($security->isLogged());
        $this->assertTrue($security->isAdmin());

        $security->updateSession(true);
        $this->assertNotEmpty($security->getSessionKey(Security::ADMIN_ID_TOKEN), 'Error saving sessions');
        return $security;

    }

    /**
     * @param Security $security
     * @depends testSecurityUserManagement
     */
    public function testSessionHandler(Security $security) {

        $testValue = mt_rand(0, 1e5);
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
        //$this->assertNotEquals($sessionId, session_id(), 'An error occurred trying to close the session');
    }

}