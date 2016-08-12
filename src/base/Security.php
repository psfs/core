<?php

    namespace PSFS\base;

    use PSFS\base\types\SingletonTrait;


    /**
     * Class Security
     * @package PSFS
     */
    class Security
    {

        use SingletonTrait;
        /**
         * @var array $user
         */
        private $user;

        /**
         * @var array $admin
         */
        private $admin;

        private $authorized = FALSE;

        protected $session;

        /**
         * Constructor por defecto
         */
        public function __construct()
        {
            if (PHP_SESSION_NONE === session_status()) {
                session_start();
            }
            $this->session = (is_null($_SESSION)) ? array() : $_SESSION;
            if (NULL === $this->getSessionKey('__FLASH_CLEAR__')) {
                $this->clearFlashes();
                $this->setSessionKey('__FLASH_CLEAR__', microtime(TRUE));
            }
            $this->user = (array_key_exists(sha1('USER'), $this->session)) ? unserialize($this->session[sha1('USER')]) : NULL;
            $this->admin = (array_key_exists(sha1('ADMIN'), $this->session)) ? unserialize($this->session[sha1('ADMIN')]) : NULL;
        }

        /**
         * Método estático que devuelve los perfiles de la plataforma
         * @return array
         */
        public static function getProfiles()
        {
            return array(
                sha1('superadmin') => _('Administrador'),
                sha1('admin')      => _('Gestor'),
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
                '__ADMIN__'       => sha1('admin'),
            );
        }

        /**
         * Método que guarda los administradores
         *
         * @param $user
         *
         * @return bool
         */
        public static function save($user)
        {
            $admins = array();
            if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json')) {
                $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), TRUE);
            }
            $admins[$user['username']]['hash'] = sha1($user['username'] . $user['password']);
            $admins[$user['username']]['profile'] = $user['profile'];

            return (FALSE !== file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json', json_encode($admins, JSON_PRETTY_PRINT)));
        }

        /**
         * Servicio que actualiza los datos del usuario
         *
         * @param $user
         */
        public function updateUser($user)
        {
            $this->user = $user;
        }

        /**
         * Método que devuelve los administradores de una plataforma
         * @return array|mixed
         */
        public function getAdmins()
        {
            $admins = array();
            if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json')) {
                $admins = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json'), TRUE);
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
        public function checkAdmin($user = NULL, $pass = NULL)
        {
            Logger::log('Checking admin session');
            if (!$this->authorized) {
                $request = Request::getInstance();
                if (!file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json')) {
                    //Si no hay fichero de usuarios redirigimos directamente al gestor
                    return Router::getInstance()->getAdmin()->adminers();
                }
                $admins = $this->getAdmins();
                //Sacamos las credenciales de la petición
                $user = $user ?: $request->getServer('PHP_AUTH_USER');
                $pass = $pass ?: $request->getServer('PHP_AUTH_PW');
                if (NULL === $user || (array_key_exists($user, $admins) && empty($admins[$user]))) {
                    list($user, $pass) = $this->getAdminFromCookie();
                }
                if (!empty($user) && !empty($admins[$user])) {
                    $auth = $admins[$user]['hash'];
                    $this->authorized = ($auth == sha1($user . $pass));
                    $this->admin = array(
                        'alias'   => $user,
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
            return substr(md5('admin'), 0, 8);
        }

        /**
         * Método que devuelve el usuario logado
         * @return user
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
            return $this->admin || preg_match('/^\/admin\/login/i', Request::requestUri());
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
         * Servicio que chequea si un usuario es super administrador o no
         * @return bool
         */
        public function isSuperAdmin()
        {
            $users = $this->getAdmins();
            $logged = $this->getAdminFromCookie();
            $profiles = Security::getCleanProfiles();
            if ($users[$logged[0]]) {
                $security = $users[$logged[0]]['profile'];

                return $profiles['__SUPER_ADMIN__'] === $security;
            }

            return FALSE;
        }

        /**
         * Servicio que devuelve un dato de sesión
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
         * Servicio que setea una variable de sesión
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
         * Servicio que devuelve los mensajes flash de sesiones
         * @return mixed
         */
        public function getFlashes()
        {
            $flashes = $this->getSessionKey(sha1('FLASHES'));

            return (NULL !== $flashes) ? $flashes : array();
        }

        /**
         * Servicio que limpia los mensajes flash
         * @return $this
         */
        public function clearFlashes()
        {
            $this->setSessionKey(sha1('FLASHES'), NULL);

            return $this;
        }

        /**
         * Servicio que inserta un flash en sesión
         *
         * @param string $key
         * @param mixed $data
         */
        public function setFlash($key, $data = NULL)
        {
            $flashes = $this->getFlashes();
            if (!is_array($flashes)) {
                $flashes = array();
            }
            $flashes[$key] = $data;
            $this->setSessionKey(sha1('FLASHES'), $flashes);
        }

        /**
         * Servicio que devuelve un flash de sesión
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
         * Servicio que actualiza
         *
         * @param boolean $closeSession
         *
         * @return Security
         */
        public function updateSession($closeSession = FALSE)
        {
            Logger::log('Update session');
            $_SESSION = $this->session;
            $_SESSION[sha1('USER')] = serialize($this->user);
            $_SESSION[sha1('ADMIN')] = serialize($this->admin);
            if ($closeSession) {
                Logger::log('Close session');
                session_write_close();
            }
            Logger::log('Session updated');
            return $this;
        }

        /**
         * Servicio que limpia la sesión
         */
        public function closeSession()
        {
            session_destroy();
            session_regenerate_id(TRUE);
        }

        /**
         * Extract parts from token
         * @param string $token
         *
         * @return array
         */
        private static function extractTokenParts($token)
        {
            $axis = 0;
            $parts = array();
            try {
                $partLength = floor(strlen($token) / 10);
                for ($i = 0, $ct = ceil(strlen($token) / $partLength); $i < $ct; $i++) {
                    $parts[] = substr($token, $axis, $partLength);
                    $axis += $partLength;
                }
            } catch(\Exception $e) {
                $partLength = 0;
            }

            return array($partLength, $parts);
        }

        /**
         * Extract Ts and Module from token
         * @param array $parts
         * @param int $partLength
         *
         * @return array
         */
        private static function extractTsAndMod(array &$parts, $partLength)
        {
            $ts = '';
            $mod = '';
            foreach ($parts as &$part) {
                if (strlen($part) == $partLength) {
                    $ts .= substr($part, 0, 1);
                    $mod .= substr($part, $partLength - 2, 2);
                    $part = substr($part, 1, $partLength - 3);
                }
            }
            return array($ts, $mod);
        }

        /**
         * Decode token to check authorized request
         * @param string $token
         * @param string $module
         *
         * @return null|string
         */
        private static function decodeToken($token, $module = 'PSFS')
        {
            $decoded = NULL;
            list($partLength, $parts) = self::extractTokenParts($token);
            list($ts, $mod) = self::extractTsAndMod($parts, $partLength);
            $hashMod = substr(strtoupper(sha1($module)), strlen($ts) / 2, strlen($ts) * 2);
            if (time() - (integer)$ts < 300 && $hashMod === $mod) {
                $decoded = implode('', $parts);
            }
            return $decoded;
        }

        /**
         * Generate a authorized token
         * @param string $secret
         * @param string $module
         *
         * @return string
         */
        public static function generateToken($secret, $module = 'PSFS')
        {
            $ts = time();
            $hashModule = substr(strtoupper(sha1($module)), strlen($ts) / 2, strlen($ts) * 2);
            $hash = hash('sha256', $secret);
            $insert = floor(strlen($hash) / strlen($ts));
            $j = 0;
            $token = '';
            for ($i = 0, $ct = strlen($ts); $i < $ct; $i++) {
                $token .= substr($ts, $i, 1) . substr($hash, $j, $insert) . substr($hashModule, $i, 2);
                $j += $insert;
            }
            $token .= substr($hash, ($insert * strlen($ts)), strlen($hash) - ($insert * strlen($ts)));
            return $token;
        }

        /**
         * Checks if auth token is correct
         * @param string $token
         * @param string $secret
         * @param string $module
         *
         * @return bool
         */
        public static function checkToken($token, $secret, $module = 'PSFS')
        {
            if (0 === strlen($token) || 0 === strlen($secret)) {
                return false;
            }
            $module = strtolower($module);
            $decodedToken = self::decodeToken($token, $module);
            $expectedToken = self::decodeToken(self::generateToken($secret, $module), $module);

            return $decodedToken === $expectedToken;
        }

    }
