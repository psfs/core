<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\AdminFileHelper;
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
        AdminFileHelper::tightenOnce();
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
        Logger::log('Checking admin session', LOG_DEBUG, null, false, 'security');
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

    /**
     * Credentials sent in the request headers (Basic, the PSFS token, JWT) authenticate that request and nothing more:
     * no session is created for them, no id is rotated and no cookie is issued, so a client that sends them with every
     * call does not leave a session in the store for each one. A session that already exists (the request carries its
     * cookie) gets a new id when the identity is established, because the privileges change (session fixation).
     * Credentials that were typed in ($pass is known) are a login: the session is created, rotated and the cookie issued.
     */
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
        $login = is_string($pass) && '' !== $pass;
        if ($login) {
            $this->ensureSessionStarted();
        }
        if (!$this->establishSessionIdentity($login)) {
            $this->authorized = false;
            $this->admin = null;
            $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, null);
            Logger::log('Admin authentication rejected because the session ID could not be rotated', LOG_WARNING, null, false, 'security');
            return;
        }
        $this->updateAdmin($user, $admins[$user]['profile']);
        if ($login) {
            $this->issueAdminCookie($user, (string)$pass);
        }
        $this->rememberInSession(AuthHelper::ADMIN_ID_TOKEN, $this->admin);
    }

    /**
     * @return bool false when the identity can not be bound to its session safely
     */
    private function establishSessionIdentity(bool $login): bool
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            // a login needs its session; a call authenticated by headers does not
            return !$login;
        }

        return null !== $this->admin || false !== @session_regenerate_id(true);
    }

    private function issueAdminCookie(string $user, string $pass): void
    {
        $encrypted = AuthHelper::encryptCookieCredentials("$user:$pass");
        if (false === $encrypted) {
            Logger::log('Admin auth cookie was not issued because auth.cookie.secret is missing or invalid', LOG_ERR, null, false, 'security');
            return;
        }
        ResponseHelper::setCookieHeaders([
            [
                'name' => AuthHelper::generateProfileHash(),
                'value' => $encrypted,
                'http' => true,
                'domain' => '',
            ]
        ]);
    }

    /**
     * @return bool
     */
    public function canAccessRestrictedAdmin()
    {
        return (null !== $this->admin && !preg_match('/^\/admin\/login/i', (string)Request::requestUri())) || self::isTest();
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
            Logger::log('[LegacyFallback] session_serialized_' . $key, LOG_NOTICE, null, false, 'security');
            return $legacyData;
        }

        return null;
    }

}
