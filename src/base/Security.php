<?php

namespace PSFS\base;

use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\SecureTrait;
use PSFS\base\types\traits\Security\FlashesTrait;
use PSFS\base\types\traits\Security\ProfileTrait;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\base\types\traits\TestTrait;

/**
 * Class Security
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
     * @var bool $authorized
     */
    private $authorized = false;

    /**
     * @var bool $checked
     */
    private $checked = false;

    /**
     * Constructor por defecto
     */
    public function init()
    {
        $this->initSession();
        if (NULL === $this->getSessionKey('__FLASH_CLEAR__')) {
            $this->clearFlashes();
            $this->setSessionKey('__FLASH_CLEAR__', microtime(TRUE));
        }
        $this->user = $this->hasSessionKey(AuthHelper::USER_ID_TOKEN) ? unserialize($this->getSessionKey(AuthHelper::USER_ID_TOKEN)) : null;
        $this->admin = $this->hasSessionKey(AuthHelper::ADMIN_ID_TOKEN) ? unserialize($this->getSessionKey(AuthHelper::ADMIN_ID_TOKEN)) : null;
        if (null === $this->admin) {
            $this->checkAdmin();
        }
        $this->setLoaded(true);
    }

    /**
     * @return array|null
     */
    public function getAdmins()
    {
        return Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', Cache::JSONGZ, true);
    }

    /**
     * @param string $user
     * @param string $pass
     * @param boolean $force
     *
     * @return bool
     */
    public function checkAdmin($user = NULL, $pass = NULL, $force = false)
    {
        Logger::log('Checking admin session');
        if ((!$this->authorized && !$this->checked) || $force) {
            $admins = $this->getAdmins();
            $token = null;
            if (null !== $admins) {
                if(empty($user)) {
                    // First try, traditional basic auth
                    Inspector::stats('[Auth] Checking Basic Auth');
                    list($user, $token) = AuthHelper::checkBasicAuth($user, $pass, $admins);
                }
                if(empty($user)) {
                    // Second try, cookie auth
                    Inspector::stats('[Auth] Checking Basic Auth PSFS');
                    list($user, $token) = AuthHelper::checkComplexAuth($admins);
                }
                if (!empty($user) && !empty($admins[$user])) {
                    $auth = $admins[$user]['hash'];
                    $this->authorized = ($auth === $token);
                    if ($this->authorized) {
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
                        $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, $encrypted);
                    }
                } else {
                    $this->admin = null;
                    $this->setSessionKey(AuthHelper::ADMIN_ID_TOKEN, null);
                }
                $this->checked = true;
            }
        }

        return $this->authorized || self::isTest();
    }

    /**
     * Método que calcula si se está logado o para acceder a administración
     * @return bool
     */
    public function canAccessRestrictedAdmin()
    {
        return (null !== $this->admin && !preg_match('/^\/admin\/login/i', Request::requestUri())) || self::isTest();
    }

    /**
     * Servicio que devuelve una pantalla de error porque se necesita estar authenticado
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

        return FALSE;
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


}
