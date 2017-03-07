<?php
namespace PSFS\test\base;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Security;

/**
 * Class SecurityTest
 * @package PSFS\test\base
 */
class SecurityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test to check if the Logger has been created successful
     * @return Security
     */
    public function getInstance()
    {
        $instance = Security::getInstance();

        $this->assertNotNull($instance, 'Security instance is null');
        $this->assertInstanceOf("\\PSFS\\base\\Security", $instance, 'Instance is different than expected');
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
        $this->assertTrue(in_array(Security::ADMIN_ID_TOKEN, $cleanProfiles), 'Key not exists');
        $this->assertTrue(in_array(Security::MANAGER_ID_TOKEN, $cleanProfiles), 'Key not exists');
        $this->assertTrue(in_array(Security::USER_ID_TOKEN, $cleanProfiles), 'Key not exists');
    }

    public function testSecurityUserManagement() {
        $security = $this->getInstance();
        $user = [
            'username' => uniqid('test'),
            'password' => uniqid('test'),
            'profile' => Security::ADMIN_ID_TOKEN,
        ];
        $security->saveUser($user);

        $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', 'Error trying to save admins');
        $this->assertNull($security->getUser());
        $this->assertNull($security->getAdmin());
        $security->updateUser($user);
        $this->assertNotNull($security->getUser(), 'An error occurred when update user in session');

        $this->assertFalse($security->checkAdmin(uniqid('test'),uniqid('error'), true), 'Error checking admin user');
        $this->assertNull($security->getAdmin(), 'Wrong admin parser');
        $this->assertTrue($security->checkAdmin($user['username'], $user['password'], true), 'An error ocurred verifying the admin user');
        $admin = $security->getAdmin();
        $this->assertNotNull($admin, 'An error ocurred gathering the admin user');
        $this->assertEquals($admin['alias'], $user['username'], 'Wrong data gathered from admins.json');
        $this->assertEquals($admin['profile'], $user['profile'], 'Wrogn profile gathered from admins.json');
    }
}