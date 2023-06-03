<?php

namespace PSFS\base;

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
    // sha1('user')
    const USER_ID_TOKEN = '12dea96fec20593566ab75692c9949596833adc9';
    // sha1('admin')
    const MANAGER_ID_TOKEN = 'd033e22ae348aeb5660fc2140aec35850c4da997';
    // sha1('superadmin')
    const ADMIN_ID_TOKEN = '889a3a791b3875cfae413574b53da4bb8a90d53e';
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
    private $authorized = FALSE;

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
        $this->user = $this->hasSessionKey(self::USER_ID_TOKEN) ? unserialize($this->getSessionKey(self::USER_ID_TOKEN)) : null;
        $this->admin = $this->hasSessionKey(self::ADMIN_ID_TOKEN) ? unserialize($this->getSessionKey(self::ADMIN_ID_TOKEN)) : null;
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
            if (null !== $admins) {
                $request = Request::getInstance();
                //Sacamos las credenciales de la petición
                $user = $user ?: $request->getServer('PHP_AUTH_USER');
                $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
                if (NULL === $user || (array_key_exists($user, $admins) && empty($admins[$user]))) {
                    list($user, $pass) = $this->getAdminFromCookie();
                }
                if (!empty($user) && !empty($admins[$user])) {
                    $auth = $admins[$user]['hash'];
                    $this->authorized = ($auth === sha1($user . $pass));
                    if ($this->authorized) {
                        $this->updateAdmin($user, $admins[$user]['profile']);
                        ResponseHelper::setCookieHeaders([
                            [
                                'name' => $this->getHash(),
                                'value' => base64_encode("$user:$pass"),
                                'http' => true,
                                'domain' => '',
                            ]
                        ]);
                        $this->setSessionKey(self::LOGGED_USER_TOKEN, base64_encode("{$user}:{$pass}"));
                    }
                } else {
                    $this->admin = null;
                    $this->setSessionKey(self::ADMIN_ID_TOKEN, null);
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
    public function canAccessRestrictedAdmin(): bool
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
            return self::ADMIN_ID_TOKEN === $security;
        }

        return FALSE;
    }

}
