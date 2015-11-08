<?php

    namespace PSFS\base;

    use PSFS\base\config\Config;
    use PSFS\base\exception\AccessDeniedException;
    use PSFS\base\exception\ConfigException;
    use PSFS\base\exception\RouterException;
    use PSFS\base\types\SingletonTrait;
    use PSFS\controller\Admin;
    use PSFS\services\AdminServices;
    use Symfony\Component\Finder\Finder;


    /**
     * Class Router
     * @package PSFS
     */
    class Router {

        use SingletonTrait;

        protected $routing;
        protected $slugs;
        private $domains;
        /**
         * @var Finder $finder
         */
        private $finder;
        /**
         * @var \PSFS\base\Cache $cache
         */
        private $cache;
        /**
         * @var \PSFS\base\Security $session
         */
        private $session;

        /**
         * Constructor Router
         * @throws ConfigException
         */
        public function __construct() {
            $this->finder = new Finder();
            $this->cache = Cache::getInstance();
            $this->session = Security::getInstance();
            $this->init();
        }

        /**
         * Inicializador Router
         * @throws ConfigException
         */
        public function init() {
            if (!file_exists(CONFIG_DIR.DIRECTORY_SEPARATOR."urls.json") || Config::getInstance()->getDebugMode()) {
                $this->hydrateRouting();
                $this->simpatize();
            }else {
                list($this->routing, $this->slugs) = $this->cache->getDataFromFile(CONFIG_DIR.DIRECTORY_SEPARATOR."urls.json", Cache::JSON, true);
                $this->domains = $this->cache->getDataFromFile(CONFIG_DIR.DIRECTORY_SEPARATOR."domains.json", Cache::JSON, true);
            }
        }

        /**
         * Método que deriva un error HTTP de página no encontrada
         *
         * @param \Exception $e
         *
         * @return string HTML
         */
        public function httpNotFound(\Exception $e = null) {
            $template = Template::getInstance()
                ->setStatus($e->getCode());
            if(preg_match('/json/i', Request::getInstance()->getServer('CONTENT_TYPE'))) {
                return $template->output(json_encode(array(
                    "success" => false,
                    "error" => $e->getMessage(),
                )), 'application/json');
            } else {
                if (null === $e) {
                    $e = new \Exception(_('Página no encontrada'), 404);
                }
                return $template->render('error.html.twig', array(
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                        'error_page' => true,
                    ));
            }
        }

        /**
         * Método que devuelve las rutas
         * @return string|null
         */
        public function getSlugs() { return $this->slugs; }

        /**
         * Método que calcula el objeto a enrutar
         * @param string $route
         * @throws \Exception
         * @return string HTML
         */
        public function execute($route) {
            try {
                // Checks restricted access
                $this->checkRestrictedAccess($route);
                //Search action and execute
                return $this->searchAction($route);
            }catch (AccessDeniedException $e) {
                Logger::getInstance()->debugLog(_('Solicitamos credenciales de acceso a zona restringida'));
                if ('login' === Config::getInstance()->get('admin_login')) {
                    return $this->redirectLogin($route);
                }else {
                    return $this->sentAuthHeader();
                }
            }catch (RouterException $r) {
                if (false !== preg_match('/\/$/', $route))
                {
                    if (preg_match('/admin/', $route)) {
                        $default = Config::getInstance()->get('admin_action');
                    }else {
                        $default = Config::getInstance()->get('home_action');
                    }
                    return $this->execute($this->getRoute($default));
                }
            }catch (\Exception $e) {
                Logger::getInstance()->errorLog($e->getMessage());
                throw $e;
            }

            return $this->httpNotFound();
        }

        /**
         * Método que busca el componente que ejecuta la ruta
         * @param string $route
         *
         * @throws \Exception
         */
        protected function searchAction($route) {
            //Revisamos si tenemos la ruta registrada
            $parts = parse_url($route);
            $path = (array_key_exists('path', $parts)) ? $parts['path'] : $route;
            $httpRequest = Request::getInstance()->getMethod();
            foreach ($this->routing as $pattern => $action) {
                list($httpMethod, $routePattern) = $this->extractHttpRoute($pattern);
                $matched = $this->matchRoutePattern($routePattern, $path);
                if ($matched && ($httpMethod === "ALL" || $httpRequest === $httpMethod)) {
                    $get = $this->extractComponents($route, $routePattern);
                    /** @var $class \PSFS\base\types\Controller */
                    $class = $this->getClassToCall($action);
                    try {
                        return $this->executeCachedRoute($route, $action, $class, $get);
                    }catch (\Exception $e)
                    {
                        Logger::getInstance()->debugLog($e->getMessage(), array($e->getFile(), $e->getLine()));
                        throw new RouterException($e->getMessage(), 404, $e);
                    }
                }
            }
            throw new RouterException(_("Ruta no encontrada"));
        }

        /**
         * Método que manda las cabeceras de autenticación
         * @return string HTML
         */
        protected function sentAuthHeader() {
            return AdminServices::getInstance()->setAdminHeaders();
        }

        /**
         * Método que redirige a la pantalla web del login
         * @param string $route
         *
         * @return string HTML
         */
        public function redirectLogin($route) {
            return Admin::staticAdminLogon($route);
        }

        /**
         * Método que chequea el acceso a una zona restringida
         * @param string $route
         *
         * @throws AccessDeniedException
         */
        protected function checkRestrictedAccess($route) {
            //Chequeamos si entramos en el admin
            if (preg_match('/^\/admin/i', $route)
                || (!preg_match('/^\/(admin|setup\-admin)/i', $route) && null !== Config::getInstance()->get('restricted'))) {
                if (!preg_match('/^\/admin\/login/i', $route) && !$this->session->checkAdmin()) {
                    throw new AccessDeniedException();
                }
                Logger::getInstance()->debugLog('Acceso autenticado al admin');
            }
        }

        /**
         * Método que extrae de la url los parámetros REST
         * @param string $route
         *
         * @param string $pattern
         * @return array
         */
        protected function extractComponents($route, $pattern) {
            $url = parse_url($route);
            $_route = explode("/", $url['path']);
            $_pattern = explode("/", $pattern);
            $get = array();
            if (!empty($_pattern)) foreach ($_pattern as $index => $component) {
                $_get = array();
                preg_match_all('/^\{(.*)\}$/i', $component, $_get);
                if (!empty($_get[1]) && isset($_route[$index])) {
                    $get[array_pop($_get[1])] = $_route[$index];
                }
            }
            return $get;
        }

        /**
         * Método que regenera el fichero de rutas
         * @throws ConfigException
         */
        private function hydrateRouting() {
            $base = SOURCE_DIR;
            $modules = realpath(CORE_DIR);
            $this->routing = $this->inspectDir($base, "PSFS", array());
            if (file_exists($modules)) {
                $this->routing = $this->inspectDir($modules, "", $this->routing);
            }
            $this->cache->storeData(CONFIG_DIR.DIRECTORY_SEPARATOR."domains.json", $this->domains, Cache::JSON, true);
            $home = Config::getInstance()->get('home_action');
            if (null !== $home || $home !== '') {
                $home_params = null;
                foreach ($this->routing as $pattern => $params) {
                    list($method, $route) = $this->extractHttpRoute($pattern);
                    if (preg_match("/".preg_quote($route, "/")."$/i", "/".$home)) {
                        $home_params = $params;
                    }
                }
                if (null !== $home_params) {
                    $this->routing['/'] = $home_params;
                }
            }
        }

        /**
         * Método que inspecciona los directorios en busca de clases que registren rutas
         * @param string $origen
         * @param string $namespace
         * @param array $routing
         * @return array
         * @throws ConfigException
         */
        private function inspectDir($origen, $namespace = 'PSFS', $routing) {
            $files = $this->finder->files()->in($origen)->path('/(controller|api)/i')->name("*.php");
            foreach ($files as $file) {
                $filename = str_replace("/", '\\', str_replace($origen, '', $file->getPathname()));
                $routing = $this->addRouting($namespace.str_replace('.php', '', $filename), $routing);
            }
            $this->finder = new Finder();
            return $routing;
        }

        /**
         * Método que añade nuevas rutas al array de referencia
         * @param string $namespace
         * @param array $routing
         * @return array
         * @throws ConfigException
         */
        private function addRouting($namespace, $routing) {
            if (class_exists($namespace)) {
                $reflection = new \ReflectionClass($namespace);
                if (false === $reflection->isAbstract() && false === $reflection->isInterface()) {
                    $this->extractDomain($reflection);
                    $classComments = $reflection->getDocComment();
                    preg_match('/@api\ (.*)\n/im', $classComments, $apiPath);
                    $api = '';
                    if(count($apiPath)) {
                        $api = array_key_exists(1, $apiPath) ? $apiPath[1] : $api;
                    }
                    foreach ($reflection->getMethods() as $method) {
                        if ($method->isPublic()) {
                            $docComments = $method->getDocComment();
                            preg_match('/@route\ (.*)\n/i', $docComments, $sr);
                            if (count($sr)) {
                                list($regex, $default, $params) = $this->extractReflectionParams($sr, $method);
                                if(strlen($api)) {
                                    $regex = str_replace('{__API__}', $api, $regex);
                                    $default = str_replace('{__API__}', $api, $default);
                                }
                                $httpMethod = $this->extractReflectionHttpMethod($docComments);
                                $visible = $this->extractReflectionVisibility($docComments);
                                $expiration = $this->extractReflectionCacheability($docComments);
                                $routing[$httpMethod."#|#".$regex] = array(
                                    "class" => $namespace,
                                    "method" => $method->getName(),
                                    "params" => $params,
                                    "default" => $default,
                                    "visible" => $visible,
                                    "http" => $httpMethod,
                                    "cache" => $expiration,
                                );
                            }
                        }
                    }
                }
            }
            return $routing;
        }

        /**
         * Método que extrae de la ReflectionClass los datos necesarios para componer los dominios en los templates
         *
         * @param \ReflectionClass $class
         *
         * @return Router
         * @throws ConfigException
         */
        protected function extractDomain($class) {
            //Calculamos los dominios para las plantillas
            if ($class->hasConstant("DOMAIN")) {
                $domain = "@".$class->getConstant("DOMAIN")."/";
                $path = dirname($class->getFileName()).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR;
                $path = realpath($path).DIRECTORY_SEPARATOR;
                $tpl_path = "templates";
                $public_path = "public";
                $model_path = "models";
                if (!preg_match("/ROOT/", $domain)) {
                    $tpl_path = ucfirst($tpl_path);
                    $public_path = ucfirst($public_path);
                    $model_path = ucfirst($model_path);
                }
                if ($class->hasConstant("TPL")) {
                    $tpl_path .= DIRECTORY_SEPARATOR.$class->getConstant("TPL");
                }
                $this->domains[$domain] = array(
                    "template" => $path.$tpl_path,
                    "model" => $path.$model_path,
                    "public" => $path.$public_path,
                );
            }
            return $this;
        }

        /**
         * Método que genera las urls amigables para usar dentro del framework
         * @return Router
         */
        private function simpatize() {
            $translationFileName = "translations".DIRECTORY_SEPARATOR."routes_translations.php";
            $absoluteTranslationFileName = CACHE_DIR.DIRECTORY_SEPARATOR.$translationFileName;
            $this->generateSlugs($absoluteTranslationFileName);
            Config::createDir(CONFIG_DIR);
            Cache::getInstance()->storeData(CONFIG_DIR.DIRECTORY_SEPARATOR."urls.json", array($this->routing, $this->slugs), Cache::JSON, true);
            return $this;
        }

        /**
         * Método que devuelve el slug de un string dado
         * @param string $text
         *
         * @return string
         */
        private function slugify($text) {
            // replace non letter or digits by -
            $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

            // trim
            $text = trim($text, '-');

            // transliterate
            if (function_exists('iconv')) {
                $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
            }

            // lowercase
            $text = strtolower($text);

            // remove unwanted characters
            $text = preg_replace('~[^-\w]+~', '', $text);

            if (empty($text)) {
                return 'n-a';
            }

            return $text;
        }

        /**
         * Método que devuelve una ruta del framework
         *
         * @param string $slug
         * @param boolean $absolute
         * @param array $params
         *
         * @return string|null
         * @throws RouterException
         */
        public function getRoute($slug = '', $absolute = false, $params = null) {
            if (strlen($slug) === 0) {
                return ($absolute) ? Request::getInstance()->getRootUrl().'/' : '/';
            }
            if (null === $slug || !array_key_exists($slug, $this->slugs)) {
                throw new RouterException(_("No existe la ruta especificada"));
            }
            $url = ($absolute) ? Request::getInstance()->getRootUrl().$this->slugs[$slug] : $this->slugs[$slug];
            if (!empty($params)) foreach ($params as $key => $value) {
                $url = str_replace("{".$key."}", $value, $url);
            } elseif (!empty($this->routing[$this->slugs[$slug]]["default"])) {
                $url = ($absolute) ? Request::getInstance()->getRootUrl().$this->routing[$this->slugs[$slug]]["default"] : $this->routing[$this->slugs[$slug]]["default"];
            }
            return preg_replace('/(GET|POST|PUT|DELETE|ALL)\#\|\#/', '', $url);
        }

        /**
         * Método que devuelve las rutas de administración
         * @return array
         */
        public function getAdminRoutes() {
            $routes = array();
            foreach ($this->routing as $route => $params) {
                list($httpMethod, $routePattern) = $this->extractHttpRoute($route);
                if (preg_match('/^\/admin(\/|$)/', $routePattern)) {
                    if (preg_match('/^PSFS/', $params["class"])) {
                        $profile = "superadmin";
                    }else {
                        $profile = "admin";
                    }
                    if (!empty($params["default"]) && $params["visible"] && preg_match('/(GET|ALL)/i', $httpMethod)) {
                        $routes[$profile][] = $params["slug"];
                    }
                }
            }
            if (array_key_exists("superadmin", $routes)) {
                asort($routes["superadmin"]);
            }
            if (array_key_exists('admin', $routes)) {
                asort($routes["admin"]);
            }
            return $routes;
        }

        /**
         * Método que devuelve le controlador del admin
         * @return Admin
         */
        public function getAdmin() {
            return Admin::getInstance();
        }

        /**
         * Método que extrae los dominios
         * @return array|null
         */
        public function getDomains() {
            return $this->domains;
        }

        /**
         * Método que extrae el controller a invocar
         * @param string $action
         * @return Object
         */
        protected function getClassToCall($action) {
            $class = (method_exists($action["class"], "getInstance")) ? $action["class"]::getInstance() : new $action["class"];
            if (null !== $class && method_exists($class, "init")) {
                $class->init();
            }
            return $class;
        }

        /**
         * Método que compara la ruta web con la guardada en la cache
         * @param $routePattern
         * @param $path
         *
         * @return bool
         */
        protected function matchRoutePattern($routePattern, $path) {
            $expr = preg_replace('/\{(.*)\}/', '###', $routePattern);
            $expr = preg_quote($expr, '/');
            $expr = str_replace('###', '(.*)', $expr);
            $expr2 = preg_replace('/\(\.\*\)$/', '', $expr);
            $matched = preg_match('/^'.$expr.'\/?$/i', $path) || preg_match('/^'.$expr2.'?$/i', $path);

            return $matched;
        }

        /**
         * @param $pattern
         *
         * @return array
         */
        protected function extractHttpRoute($pattern) {
            $httpMethod = "ALL";
            $routePattern = $pattern;
            if (FALSE !== strstr($pattern, "#|#")) {
                list($httpMethod, $routePattern) = explode("#|#", $pattern, 2);
            }

            return array($httpMethod, $routePattern);
        }

        /**
         * Método que extrae los parámetros de una función
         * @param array $sr
         * @param \ReflectionMethod $method
         *
         * @return array
         */
        private function extractReflectionParams($sr, $method) {
            $regex = $sr[1] ?: $sr[0];
            $default = '';
            $params = array();
            $parameters = $method->getParameters();
            if (count($parameters) > 0) foreach ($parameters as $param) {
                if ($param->isOptional() && !is_array($param->getDefaultValue())) {
                    $params[$param->getName()] = $param->getDefaultValue();
                    $default = str_replace('{'.$param->getName().'}', $param->getDefaultValue(), $regex);
                }
            }else $default = $regex;
            return array($regex, $default, $params);
        }

        /**
         * Método que extrae el método http
         * @param string $docComments
         *
         * @return string
         */
        private function extractReflectionHttpMethod($docComments) {
            preg_match('/@(GET|POST|PUT|DELETE)\n/i', $docComments, $routeMethod);
            return (count($routeMethod) > 0) ? $routeMethod[1] : "ALL";
        }

        /**
         * Método que extrae la visibilidad de una ruta
         * @param string $docComments
         *
         * @return bool
         */
        private function extractReflectionVisibility($docComments) {
            preg_match('/@visible\ (.*)\n/i', $docComments, $visible);
            return (!empty($visible) && isset($visible[1]) && $visible[1] == 'false') ? FALSE : TRUE;
        }

        /**
         * Método que extrae el parámetro de caché
         * @param string $docComments
         *
         * @return bool
         */
        private function extractReflectionCacheability($docComments) {
            preg_match('/@cache\ (.*)\n/i', $docComments, $cache);
            return (count($cache) > 0) ? $cache[1] : "0";
        }

        /**
         * Método que ejecuta una acción del framework y revisa si lo tenemos cacheado ya o no
         * @param string $route
         * @param array $action
         * @param types\Controller $class
         * @param array $params
         */
        protected function executeCachedRoute($route, $action, $class, $params = null) {
            Logger::getInstance()->debugLog(_('Ruta resuelta para ').$route);
            $this->session->setSessionKey("__CACHE__", $action);
            $cache = Cache::needCache();
            $execute = true;
            if (false !== $cache && Config::getInstance()->getDebugMode() === false) {
                $cacheDataName = $this->cache->getRequestCacheHash();
                $cachedData = $this->cache->readFromCache("templates".DIRECTORY_SEPARATOR.$cacheDataName, $cache, function() {});
                if (null !== $cachedData) {
                    $headers = $this->cache->readFromCache("templates".DIRECTORY_SEPARATOR.$cacheDataName.".headers", $cache, function() {}, Cache::JSON);
                    Template::getInstance()->renderCache($cachedData, $headers);
                    $execute = false;
                }
            }
            if ($execute) {
                call_user_func_array(array($class, $action['method']), $params);
            }
        }

        /**
         * Parse slugs to create translations
         * @param string $absoluteTranslationFileName
         */
        private function generateSlugs($absoluteTranslationFileName)
        {
            $translations = $this->generateTranslationsFile($absoluteTranslationFileName);
            foreach ($this->routing as $key => &$info) {
                if (preg_match('/(ALL|GET)/i', $key)) {
                    $keyParts = $key;
                    if (FALSE === strstr("#|#", $key)) {
                        $keyParts = explode("#|#", $key);
                        $keyParts = $keyParts[1];
                    }
                    $slug = $this->slugify($keyParts);
                    if (NULL !== $slug && !array_key_exists($slug, $translations)) {
                        $translations[$slug] = $key;
                        file_put_contents($absoluteTranslationFileName, "\$translations[\"{$slug}\"] = _(\"{$slug}\");\n", FILE_APPEND);
                    }
                    $this->slugs[$slug] = $key;
                    $info["slug"] = $slug;
                }
            }
        }

        /**
         * Create translation file if not exists
         * @param string $absoluteTranslationFileName
         *
         * @return array
         */
        private function generateTranslationsFile($absoluteTranslationFileName)
        {
            $translations = array();
            if (file_exists($absoluteTranslationFileName)) {
                include($absoluteTranslationFileName);
            } else {
                Cache::getInstance()->storeData($absoluteTranslationFileName, "<?php \$translations = array();\n", Cache::TEXT, TRUE);
            }
            return $translations;
        }
    }
