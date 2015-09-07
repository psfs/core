<?php

namespace PSFS\base;
use PSFS\base\types\SingletonTrait;


/**
 * Class Security
 * @package PSFS
 */
class Security {

    use SingletonTrait;
    /**
     * @var array user
     */
    private $user;

    private $authorized = false;

    protected $session;

    /**
     * Constructor por defecto
     */
    public function __construct(){
        session_start();
        $this->session = (is_null($_SESSION)) ? array() : $_SESSION;
        if(null === $this->getSessionKey('__FLASH_CLEAR__')) {
            $this->clearFlashes();
            $this->setSessionKey('__FLASH_CLEAR__', microtime(true));
        }
        $this->user = (array_key_exists(sha1('USER'), $this->session)) ? unserialize($this->session[sha1('USER')]) : null;
    }

    /**
     * Método estático que devuelve los perfiles de la plataforma
     * @return array
     */
    public static function getProfiles()
    {
        return array(
            sha1('superadmin') => _('Administrador'),
            sha1('admin') => _('Gestor'),
        );
    }

    /**
     * Método estático que devuelve los perfiles disponibles
     * @return array
     */
    public static function getCleanProfiles()
    {
        return array(
            '__SUPER_ADMIN__' => sha1('superadmin'),
            '__ADMIN__' => sha1('admin'),
        );
    }

    /**
     * Método que guarda los administradores
     * @param $user
     *
     * @return bool
     */
    public static function save($user)
    {
        $admins = array();
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
        {
            $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), true);
        }
        $admins[$user['username']]['hash'] = sha1($user['username'].$user['password']);
        $admins[$user['username']]['profile'] = $user['profile'];
        return (false !== file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', json_encode($admins)));
    }

    /**
     * Método que devuelve los administradores de una plataforma
     * @return array|mixed
     */
    public function getAdmins()
    {
        $admins = array();
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
        {
            $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), true);
        }
        return $admins;
    }

    /**
     * Método que devuelve si un usuario tiene privilegios para acceder a la zona de administración
     *
     * @param null $user
     * @param null $pass
     *
     * @return bool
     * @throws \HttpException
     */
    public function checkAdmin($user = null, $pass = null)
    {
        if(!$this->authorized)
        {
            $request = Request::getInstance();
            if(!file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'))
            {
                //Si no hay fichero de usuarios redirigimos directamente al gestor
                return Router::getInstance()->getAdmin()->adminers();
            }
            $admins = $this->getAdmins();
            //Sacamos las credenciales de la petición
            $user = $user ?: $request->getServer('PHP_AUTH_USER');
            $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
            if(empty($user) && empty($admins[$user]))
            {
                list($user, $pass) = $this->getAdminFromCookie();
            }
            if(!empty($user) && !empty($admins[$user]))
            {
                $auth = $admins[$user]['hash'];
                $this->authorized = ($auth == sha1($user.$pass));
                $this->user = array(
                    'alias' => $user,
                    'profile' => $admins[$user]['profile'],
                );
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
        if(!empty($auth_cookie))
        {
            list($user, $pass) = explode(':', base64_decode($auth_cookie));
        }
        return array($user, $pass);
    }

    /**
     * Método privado para la generación del hash de la cookie de administración
     * @return string
     */
    public function getHash(){ return substr(md5('admin'), 0, 8); }

    /**
     * Método que devuelve el usuario logado
     * @return user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Servicio que devuelve una pantalla de error porque se necesita estar authenticado
     * @param $route
     *
     * @return mixed
     */
    public function notAuthorized($route)
    {
        return Template::getInstance()->render('notauthorized.html.twig', array(
            'route' => $route,
        ));
    }

    /**
     * Servicio que chequea si un usuario es super administrador o no
     * @return bool
     */
    public function isSuperAdmin()
    {
        $users = $this->getAdmins();
        $logged = $this->getAdminFromCookie();
        $profiles = Security::getCleanProfiles();
        if($users[$logged[0]])
        {
            $security = $users[$logged[0]]['profile'];
            return $profiles['__SUPER_ADMIN__'] === $security;
        }
        return false;
    }

    /**
     * Servicio que devuelve un dato de sesión
     * @param string $key
     * @return mixed
     */
    public function getSessionKey($key) {
        $data = null;
        if (array_key_exists($key, $this->session)) {
            $data = $this->session[$key];
        }
        return $data;
    }

    /**
     * Servicio que setea una variable de sesión
     * @param string $key
     * @param mixed $data
     * @return Security
     */
    public function setSessionKey($key, $data = null) {
        $this->session[$key] = $data;
        return $this;
    }

    /**
     * Servicio que devuelve los mensajes flash de sesiones
     * @return mixed
     */
    public function getFlashes() {
        return $this->getSessionKey(sha1('FLASHES'));
    }

    /**
     * Servicio que limpia los mensajes flash
     * @return $this
     */
    public function clearFlashes() {
        $this->setSessionKey(sha1('FLASHES'), null);
        return $this;
    }

    /**
     * Servicio que actualiza
     * @param boolean $closeSession
     * @return Security
     */
    public function updateSession($closeSession = false) {
        $_SESSION = $this->session;
        $_SESSION[sha1('USER')] = serialize($this->user);
        if($closeSession) {
            session_write_close();
        }
        return $this;
    }
}
