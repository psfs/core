<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\SecureTrait;
use PSFS\base\types\traits\Security\FlashesTrait;
use PSFS\base\types\traits\Security\ProfileTrait;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\base\types\traits\TestTrait;

/**
 * @package PSFS
 */
class Security
{
    // sha1('FLASHES')
    const FLASH_MESSAGE_TOKEN = '4680c68435db1bfbf17c3fcc4f7b39d2c6122504';
    const LOGGED_USER_TOKEN = '__U_T_L__';

    use SecureTrait;
    use SingletonTrait;
    use TestTrait;
    use ProfileTrait;
    use FlashesTrait;

    /**
     * @var bool
     */
    private $authorized = false;

    /**
     * @var bool
     */
    private $checked = false;


    public function init()
    {
        $this->initSession();
        if (null === $this->getSessionKey('__FLASH_CLEAR__')) {
            $this->clearFlashes();
            $this->setSessionKey('__FLASH_CLEAR__', microtime(true));
        }
        $this->user = $this->readIdentityFromSession(AuthHelper::USER_ID_TOKEN);
        $this->admin = $this->readIdentityFromSession(AuthHelper::ADMIN_ID_TOKEN);
        if (null === $this->admin) {
            $this->checkAdmin();
        }
        $this->setLoaded(true);
    }

    /**
     * @return array
     */
    public function getAdmins(): array
    {
        $admins = Cache::getInstance()->getDataFromFile(
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json',
            Cache::JSONGZ,
            true
        );
        return is_array($admins) ? $admins : [];
    }

    /**
     * @param string $user
     * @param string $pass
     * @param boolean $force
     *
     * @return bool
     */
    public function checkAdmin($user = null, $pass = null, $force = false)
    {
        Logger::log('Checking admin session');
        if (!$this->shouldCheckAdmin($force)) {
            return $this->authorized || self::isTest();
        }
        $admins = $this->getAdmins();
        if (empty($admins)) {
            return $this->authorized || self::isTest();
        }
        [$user, $token] = $this->resolveAdminCredentials($admins, $user, $pass);
        $this->authorizeAdminCredentials($admins, $user, $token, $pass);
        $this->checked = true;

        return $this->authorized || self::isTest();
    }

    private function shouldCheckAdmin(bool $force): bool
    {
        return ((!$this->authorized && !$this->checked) || $force);
    }

    private function resolveAdminCredentials(array $admins, $user, $pass): array
    {
        $token = null;
        if (empty($user)) {
            Inspector::stats('[Auth] Checking Basic Auth');
            [$user, $token] = AuthHelper::checkBasicAuth($user, $pass, $admins);
        }
        if (empty($user)) {
            Inspector::stats('[Auth] Checking Basic Auth PSFS');
            [$user, $token] = AuthHelper::checkComplexAuth($admins);
        }
        if (empty($user) && Config::getParam('enable.jwt', false)) {
            Inspector::stats('[Auth] Checking JWT Auth');
            [$user, $token] = AuthHelper::checkJwtAuth($admins);
        }
        return [$user, $token];
    }

    private function authorizeAdminCredentials(array $admins, $user, $token, $pass): void
    {
        if (empty($user) || empty($admins[$user])) {
            $this->admin = null;
            $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, null);
            return;
        }
        $auth = $admins[$user]['hash'];
        $this->authorized = ($auth === $token);
        if (!$this->authorized) {
            return;
        }
        $this->updateAdmin($user, $admins[$user]['profile']);
        $encrypted = AuthHelper::encrypt("$user:$pass", AuthHelper::SESSION_TOKEN);
        ResponseHelper::setCookieHeaders([
            [
                'name' => AuthHelper::generateProfileHash(),
                'value' => $encrypted,
                'http' => true,
                'domain' => '',
            ]
        ]);
        $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, $this->admin);
    }

    /**
     * @return bool
     */
    public function canAccessRestrictedAdmin()
    {
        return (null !== $this->admin && !preg_match('/^\/admin\/login/i', Request::requestUri())) || self::isTest();
    }

    /**
     *
     * @param string|null $route
     *
     * @return string|null
     * @throws exception\GeneratorException
     */
    public function notAuthorized($route)
    {
        return Template::getInstance()->render('notauthorized.html.twig', array(
            'route' => $route,
        ));
    }

    private function checkAdminRole($role = AuthHelper::USER_ID_TOKEN)
    {
        $users = $this->getAdmins();
        $logged = $this->getAdmin();
        if (is_array($logged)
            && is_array($users)
            && array_key_exists('alias', $logged)
            && array_key_exists($logged['alias'], $users)) {
            $security = $users[$logged['alias']]['profile'];
            return $role === $security;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isSuperAdmin()
    {
        $users = $this->getAdmins();
        $logged = $this->getAdmin();
        if (is_array($logged)
            && is_array($users)
            && array_key_exists('alias', $logged)
            && array_key_exists($logged['alias'], $users)) {
            $security = $users[$logged['alias']]['profile'];
            return AuthHelper::ADMIN_ID_TOKEN === $security;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isManager()
    {
        return $this->checkAdminRole(AuthHelper::MANAGER_ID_TOKEN);
    }

    /**
     * @return bool
     */
    public function isUser()
    {
        return $this->checkAdminRole();
    }

    public function clearAdminAuthentication(): void
    {
        $this->authorized = false;
        $this->checked = false;
        $this->user = null;
        $this->admin = null;
        $this->setSessionKey(AuthHelper::USER_ID_TOKEN, null);
        $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, null);
        ResponseHelper::setAuthHeaders(true);
        ResponseHelper::setCookieHeaders([
            [
                'name' => AuthHelper::generateProfileHash(),
                'value' => '',
                'expire' => time() - 3600,
                'httpOnly' => true,
                'path' => '/',
                'domain' => '',
            ],
        ]);
        $this->closeSession();
    }

    private function readIdentityFromSession(string $key): ?array
    {
        if (!$this->hasSessionKey($key)) {
            return null;
        }
        $data = $this->getSessionKey($key);
        if (is_array($data)) {
            return $data;
        }
        if (!is_string($data) || '' === trim($data)) {
            return null;
        }
        $decoded = json_decode($data, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Legacy compatibility: historic sessions were stored serialized.
        $legacyData = @unserialize($data, ['allowed_classes' => false]);
        if (is_array($legacyData)) {
            Logger::log('[LegacyFallback] session_serialized_' . $key, LOG_NOTICE);
            return $legacyData;
        }

        return null;
    }

}
