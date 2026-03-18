<?php

namespace PSFS\base\types\traits\Security;

use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\types\helpers\AuthHelper;

/**
 * @package PSFS\base\types\traits\Security
 */
trait ProfileTrait
{
    /**
     * @var array
     */
    protected $user = null;

    /**
     * @var array
     */
    protected $admin = null;

    /**
     * @return array
     */
    public static function getProfiles()
    {
        return array(
            AuthHelper::ADMIN_ID_TOKEN => t('Administrator'),
            AuthHelper::MANAGER_ID_TOKEN => t('Manager'),
            AuthHelper::USER_ID_TOKEN => t('User'),
        );
    }

    /**
     * @return array
     */
    public function getAdminCleanProfiles()
    {
        return static::getCleanProfiles();
    }

    /**
     * @return array
     */
    public function getAdminProfiles()
    {
        return static::getProfiles();
    }

    /**
     * @return array
     */
    public static function getCleanProfiles()
    {
        return array(
            '__SUPER_ADMIN__' => AuthHelper::ADMIN_ID_TOKEN,
            '__ADMIN__' => AuthHelper::MANAGER_ID_TOKEN,
            '__USER__' => AuthHelper::USER_ID_TOKEN,
        );
    }

    /**
     * @return array
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return array
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    /**
     * @param mixed $user
     * @return bool
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function save($user)
    {
        $saved = true;
        $admins = Cache::getInstance()->getDataFromFile(
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json',
            Cache::JSONGZ,
            true
        ) ?: [];
        $admins[$user['username']]['hash'] = sha1($user['username'] . $user['password']);
        $admins[$user['username']]['profile'] = $user['profile'];

        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', $admins, Cache::JSONGZ, true);
        return $saved;
    }

    /**
     * @param $user
     * @return bool
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function saveUser($user)
    {
        $saved = false;
        if (!empty($user)) {
            $saved = self::save($user);
        }
        return $saved;
    }

    public function deleteUser($user)
    {
        $admins = Cache::getInstance()->getDataFromFile(
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json',
            Cache::JSONGZ,
            true
        ) ?: [];
        unset($admins[$user]);
        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', $admins, Cache::JSONGZ, true);
    }

    /**
     * @param mixed $user
     */
    public function updateUser($user)
    {
        $this->user = $user;
    }

    /**
     * @param $alias
     * @param $profile
     */
    public function updateAdmin($alias, $profile)
    {
        $this->admin = array(
            'alias' => $alias,
            'profile' => $profile,
        );
        $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, serialize($this->admin));
    }

    /**
     * @return array
     */
    protected function getAdminFromCookie()
    {
        $authCookie = Request::getInstance()->getCookie(AuthHelper::generateProfileHash());
        $user = $pass = array();
        if (!empty($authCookie)) {
            list($user, $pass) = explode(':', base64_decode($authCookie));
        }
        return array($user, $pass);
    }

}
