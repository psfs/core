<?php

namespace PSFS\base\types\traits\Security;

use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Security;

/**
 * Trait ProfileTrait
 * @package PSFS\base\types\traits\Security
 */
trait ProfileTrait
{
    /**
     * @var array $user
     */
    protected $user = null;

    /**
     * @var array $admin
     */
    protected $admin = null;

    /**
     * @return array
     */
    public static function getProfiles()
    {
        return array(
            Security::ADMIN_ID_TOKEN => t('Administrador'),
            Security::MANAGER_ID_TOKEN => t('Gestor'),
            Security::USER_ID_TOKEN => t('Usuario'),
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
            '__SUPER_ADMIN__' => Security::ADMIN_ID_TOKEN,
            '__ADMIN__' => Security::MANAGER_ID_TOKEN,
            '__USER__' => Security::USER_ID_TOKEN,
        );
    }

    /**
     * Método que devuelve el usuario logado
     * @return array
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Método que devuelve el usuario administrador logado
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
        $admins = Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', Cache::JSONGZ, true) ?: [];
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
        $this->setSessionKey(self::ADMIN_ID_TOKEN, serialize($this->admin));
    }

    /**
     * Método que obtiene el usuario y contraseña de la cookie de sesión de administración
     * @return array
     */
    protected function getAdminFromCookie()
    {
        $authCookie = Request::getInstance()->getCookie($this->getHash());
        $user = $pass = array();
        if (!empty($authCookie)) {
            list($user, $pass) = explode(':', base64_decode($authCookie));
        }

        return array($user, $pass);
    }

    /**
     * Método privado para la generación del hash de la cookie de administración
     * @param string $role
     * @return string
     */
    public function getHash($role = Security::MANAGER_ID_TOKEN)
    {
        return substr($role, 0, 8);
    }


}
