<?php
namespace PSFS\base;

use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\SecureTrait;
use PSFS\base\types\traits\SingletonTrait;

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
    /**
     * @var array $user
     */
    private $user = null;

    /**
     * @var array $admin
     */
    private $admin = null;

    /**
     * @var bool $authorized
     */
    private $authorized = FALSE;

    /**
     * @var bool $checked
     */
    private $checked = false;

    /**
     * @var array $session
     */
    protected $session;

    /**
     * Constructor por defecto
     */
    public function init()
    {
        $this->initSession();
        $this->session = null === $_SESSION ? array() : $_SESSION;
        if (NULL === $this->getSessionKey('__FLASH_CLEAR__')) {
            $this->clearFlashes();
            $this->setSessionKey('__FLASH_CLEAR__', microtime(TRUE));
        }
        $this->user = array_key_exists(self::USER_ID_TOKEN, $this->session) ? unserialize($this->session[self::USER_ID_TOKEN]) : NULL;
        $this->admin = array_key_exists(self::ADMIN_ID_TOKEN, $this->session) ? unserialize($this->session[self::ADMIN_ID_TOKEN]) : NULL;
        if (null === $this->admin) {
            $this->checkAdmin();
        }
        $this->setLoaded(true);
    }

    private function initSession() {
        if (PHP_SESSION_NONE === session_status() && !headers_sent()) {
            session_start();
        }
        // Fix for phpunits
        if(!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * @return array
     */
    public static function getProfiles()
    {
        return array(
            self::ADMIN_ID_TOKEN => _('Administrador'),
            self::MANAGER_ID_TOKEN => _('Gestor'),
            self::USER_ID_TOKEN => _('Usuario'),
        );
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
            '__SUPER_ADMIN__' => self::ADMIN_ID_TOKEN,
            '__ADMIN__' => self::MANAGER_ID_TOKEN,
            '__USER__' => self::USER_ID_TOKEN,
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
     * @param mixed $user
     * @return bool
     * @throws exception\GeneratorException
     * @throws ConfigException
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
     * @param mixed $user
     * @return bool
     * @throws exception\GeneratorException
     */
    public function saveUser($user)
    {
        $saved = false;
        if (!empty($user)) {
            $saved = static::save($user);
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
    public function updateAdmin($alias, $profile) {
        $this->admin = array(
            'alias' => $alias,
            'profile' => $profile,
        );
        $this->setSessionKey(self::ADMIN_ID_TOKEN, serialize($this->admin));
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
                        $this->updateAdmin($user , $admins[$user]['profile']);
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

        return $this->authorized;
    }

    /**
     * Método que obtiene el usuario y contraseña de la cookie de sesión de administración
     * @return array
     */
    protected function getAdminFromCookie()
    {
        $auth_cookie = Request::getInstance()->getCookie($this->getHash());
        $user = $pass = array();
        if (!empty($auth_cookie)) {
            list($user, $pass) = explode(':', base64_decode($auth_cookie));
        }

        return array($user, $pass);
    }

    /**
     * Método privado para la generación del hash de la cookie de administración
     * @return string
     */
    public function getHash()
    {
        return substr(self::MANAGER_ID_TOKEN, 0, 8);
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
     * Método que calcula si se está logado o para acceder a administración
     * @return bool
     */
    public function canAccessRestrictedAdmin()
    {
        return null !== $this->admin && !preg_match('/^\/admin\/login/i', Request::requestUri());
    }

    /**
     * Servicio que devuelve una pantalla de error porque se necesita estar authenticado
     *
     * @param string|null $route
     *
     * @return string|null
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

    /**
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getSessionKey($key)
    {
        $data = NULL;
        if (array_key_exists($key, $this->session)) {
            $data = $this->session[$key];
        }

        return $data;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     *
     * @return Security
     */
    public function setSessionKey($key, $data = NULL)
    {
        $this->session[$key] = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFlashes()
    {
        $flashes = $this->getSessionKey(self::FLASH_MESSAGE_TOKEN);

        return (NULL !== $flashes) ? $flashes : array();
    }

    /**
     * @return $this
     */
    public function clearFlashes()
    {
        $this->setSessionKey(self::FLASH_MESSAGE_TOKEN, NULL);

        return $this;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     */
    public function setFlash($key, $data = NULL)
    {
        $flashes = $this->getFlashes();
        if (!is_array($flashes)) {
            $flashes = [];
        }
        $flashes[$key] = $data;
        $this->setSessionKey(self::FLASH_MESSAGE_TOKEN, $flashes);
    }

    /**
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getFlash($key)
    {
        $flashes = $this->getFlashes();

        return (NULL !== $key && array_key_exists($key, $flashes)) ? $flashes[$key] : NULL;
    }

    /**
     *
     * @param boolean $closeSession
     *
     * @return Security
     */
    public function updateSession($closeSession = FALSE)
    {
        Logger::log('Update session');
        $_SESSION = $this->session;
        $_SESSION[self::USER_ID_TOKEN] = serialize($this->user);
        $_SESSION[self::ADMIN_ID_TOKEN] = serialize($this->admin);
        if ($closeSession) {
            Logger::log('Close session');
            @session_write_close();
            @session_start();
        }
        Logger::log('Session updated');
        return $this;
    }

    public function closeSession()
    {
        unset($_SESSION);
        @session_destroy();
        @session_regenerate_id(TRUE);
        @session_start();
    }

}
