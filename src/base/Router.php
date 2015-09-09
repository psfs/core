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

        public static $ROUTES_CACHE_FILENAME = CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json";
        public static $DOMAINS_CACHE_FILENAME = CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json";

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
         * Constructor Router
         * @throws ConfigException
         */
        public function __construct()
        {
            $this->finder = new Finder();
            $this->cache = Cache::getInstance();
            $this->init();
        }

        /**
         * Inicializador Router
         * @throws ConfigException
         */
        public function init() {
            if(!file_exists(Router::$ROUTES_CACHE_FILENAME) || Config::getInstance()->getDebugMode())
            {
                $this->hydrateRouting();
                $this->simpatize();
            }else
            {
                list($this->routing, $this->slugs) = $this->cache->getDataFromFile(Router::$ROUTES_CACHE_FILENAME, Cache::JSON, true);
                $this->domains = $this->cache->getDataFromFile(Router::$DOMAINS_CACHE_FILENAME, Cache::JSON, true);
            }
        }

        /**
         * Método que deriva un error HTTP de página no encontrada
         *
         * @param \Exception $e
         *
         * @return string HTML
         */
        public function httpNotFound(\Exception $e = null)
        {
            if(null === $e) {
                $e = new \Exception(_('Página no encontrada'), 404);
            }
            return Template::getInstance()
                ->setStatus($e->getCode())
                ->render('error.html.twig', array(
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'error_page' => true,
            ));
        }

        /**
         * Método que devuelve las rutas
         * @return mixed
         */
        public function getSlugs(){ return $this->slugs; }

        /**
         * Método que calcula el objeto a enrutar
         * @param $route
         * @throws \Exception
         * @return string HTML
         */
        public function execute($route)
        {
            try
            {
                // Checks restricted access
                $this->checkRestrictedAccess($route);
                //Search action and execute
                $this->searchAction($route);
            } catch (AccessDeniedException $e) {
                Logger::getInstance()->debugLog(_('Solicitamos credenciales de acceso a zona restringida'));
                if('login' === Config::getInstance()->get('admin_login')) {
                    return $this->redirectLogin($route);
                } else {
                    return $this->sentAuthHeader();
                }
            } catch (RouterException $r) {
                if(false !== preg_match('/\/$/', $route))
                {
                    if(preg_match('/admin/', $route)) {
                        $default = Config::getInstance()->get('admin_action');
                    } else {
                        $default = Config::getInstance()->get('home_action');
                    }
                    return $this->execute($this->getRoute($default));
                }
            } catch (\Exception $e) {
                Logger::getInstance()->errorLog($e->getMessage());
                throw $e;
            }

            return $this->httpNotFound();
        }

        /**
         * Método que busca el componente que ejecuta la ruta
         * @param $route
         *
         * @return mixed|boolean
         * @throws \Exception
         */
        protected function searchAction($route)
        {
            //Revisamos si tenemos la ruta registrada
            $parts = parse_url($route);
            $path = (array_key_exists('path', $parts)) ? $parts['path'] : $route;
            foreach($this->routing as $pattern => $action)
            {
                $expr = preg_replace('/\{(.*)\}/', '###', $pattern);
                $expr = preg_quote($expr, '/');
                $expr = str_replace('###', '(.*)', $expr);
                $expr2 = preg_replace('/\(\.\*\)$/', '', $expr);
                if(preg_match('/^'. $expr .'\/?$/i', $path) || preg_match('/^'. $expr2 .'?$/i', $path))
                {
                    $get = $this->extractComponents($route, $pattern);
                    /** @var $class \PSFS\base\types\Controller */
                    $class = $this->getClassToCall($action);
                    try{
                        Logger::getInstance()->debugLog(_('Ruta resuelta para ') . $route);
                        call_user_func_array(array($class, $action['method']), $get);
                    }catch(\Exception $e)
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
        protected function sentAuthHeader()
        {
            return AdminServices::getInstance()->setAdminHeaders();
        }

        /**
         * Método que redirige a la pantalla web del login
         * @param $route
         *
         * @return \PSFS\controller\html
         */
        public function redirectLogin($route)
        {
            return Admin::getInstance()->adminLogin($route);
        }

        /**
         * Método que chequea el acceso a una zona restringida
         * @param $route
         *
         * @throws AccessDeniedException
         */
        protected function checkRestrictedAccess($route)
        {
            //Chequeamos si entramos en el admin
            if(preg_match('/^\/admin/i', $route) || (!preg_match('/^\/(admin|setup\-admin)/i', $route) && null !== Config::getInstance()->get('restricted')))
            {
                if(!Security::getInstance()->checkAdmin())
                {
                    throw new AccessDeniedException();
                }
                Logger::getInstance()->debugLog('Acceso autenticado al admin');
            }
        }

        /**
         * Método que extrae de la url los parámetros REST
         * @param $route
         *
         * @param $pattern
         * @return array
        */
        protected function extractComponents($route, $pattern)
        {
            $url = parse_url($route);
            $_route = explode("/", $url['path']);
            $_pattern = explode("/", $pattern);
            $get = array();
            if(!empty($_pattern)) foreach($_pattern as $index => $component)
            {
                $_get = array();
                preg_match_all('/^\{(.*)\}$/i', $component, $_get);
                if(!empty($_get[1]) && isset($_route[$index]))
                {
                    $get[array_pop($_get[1])] = $_route[$index];
                }
            }
            return $get;
        }

        /**
         * Método que regenera el fichero de rutas
         * @throws ConfigException
         */
        private function hydrateRouting()
        {
            $base = SOURCE_DIR;
            $modules = realpath(CORE_DIR);
            $this->routing = $this->inspectDir($base, "PSFS", array());
            if(file_exists($modules)) {
                $this->routing = $this->inspectDir($modules, "", $this->routing);
            }
            Config::createDir(CONFIG_DIR);
            $this->cache->storeData(Router::$DOMAINS_CACHE_FILENAME, $this->domains, Cache::JSON, true);
            $home = Config::getInstance()->get('home_action');
            if (null !== $home || $home !== '')
            {
                $home_params = null;
                foreach ($this->routing as $pattern => $params)
                {
                    if(preg_match("/".preg_quote($pattern, "/")."$/i", "/".$home)) {
                        $home_params = $params;
                    }
                }
                if (null === $home_params) {
                    $this->routing['/'] = $home_params;
                }
            }
        }

        /**
         * Método que inspecciona los directorios en busca de clases que registren rutas
         * @param $origen
         * @param string $namespace
         * @param $routing
         * @return mixed
         * @throws ConfigException
        */
        private function inspectDir($origen, $namespace = 'PSFS', $routing)
        {
            $files = $this->finder->files()->in($origen)->path('/controller/i')->name("*.php");
            foreach($files as $file)
            {
                $filename = str_replace("/", '\\', str_replace($origen, '', $file->getPathname()));
                $routing = $this->addRouting($namespace .str_replace('.php', '', $filename), $routing);
            }
            $this->finder = new Finder();
            return $routing;
        }

        /**
         * Método que añade nuevas rutas al array de referencia
         * @param $namespace
         * @param $routing
         * @return mixed
         * @throws ConfigException
        */
        private function addRouting($namespace, $routing)
        {
            if(class_exists($namespace))
            {
                $reflection = new \ReflectionClass($namespace);
                if(false === $reflection->isAbstract() && false === $reflection->isInterface())
                {
                    $this->extractDomain($reflection);
                    foreach($reflection->getMethods() as $method)
                    {
                        if($method->isPublic())
                        {
                            preg_match('/@route\ (.*)\n/i', $method->getDocComment(), $sr);
                            if(count($sr))
                            {
                                $regex = $sr[1] ?: $sr[0];
                                $default = '';
                                $params = array();
                                $parameters = $method->getParameters();
                                if(count($parameters) > 0) foreach($parameters as $param)
                                {
                                    if($param->isOptional() && !is_array($param->getDefaultValue()))
                                    {
                                        $params[$param->getName()] = $param->getDefaultValue();
                                        $default = str_replace('{' . $param->getName() . '}', $param->getDefaultValue(), $regex);
                                    }
                                }else $default = $regex;
                                preg_match('/@visible\ (.*)\n/i', $method->getDocComment(), $visible);
                                $visible = (!empty($visible) && isset($visible[1]) && $visible[1] == 'false') ? false : true;
                                $routing[$regex] = array(
                                    "class" => $namespace,
                                    "method" => $method->getName(),
                                    "params" => $params,
                                    "default" => $default,
                                    "visible" => $visible,
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
         * @param $class
         *
         * @return $this
         * @throws ConfigException
         */
        protected function extractDomain($class)
        {
            //Calculamos los dominios para las plantillas
            if(!$class->hasConstant("DOMAIN")) $domain = "@" . get_class($class) . "/";
            else $domain = "@" . $class->getConstant("DOMAIN") . "/";
            $path = realpath(dirname($class->getFileName()) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $tpl_path = "templates";
            $public_path = "public";
            $model_path = "models";
            if(!preg_match("/ROOT/", $domain))
            {
                $tpl_path = ucfirst($tpl_path);
                $public_path = ucfirst($public_path);
                $model_path = ucfirst($model_path);
            }
            if($class->hasConstant("TPL")) $tpl_path .= DIRECTORY_SEPARATOR . $class->getConstant("TPL");
            $this->domains[$domain] = array(
                "template" => $path . $tpl_path,
                "model" => $path . $model_path,
                "public" => $path . $public_path,
            );
            return $this;
        }

        /**
         * Método que genera las urls amigables para usar dentro del framework
         * @return $this
         */
        private function simpatize()
        {
            foreach($this->routing as $key => &$info)
            {
                $slug = $this->slugify($key);
                Config::createDir(CACHE_DIR . DIRECTORY_SEPARATOR . "translations");
                if(!file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php")) file_put_contents(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php", "<?php \$translations = array();\n");
                include(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php");
                if(!isset($translations[$slug])) file_put_contents(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php", "\$translations[\"{$slug}\"] = _(\"{$slug}\");\n", FILE_APPEND);
                $this->slugs[$slug] = $key;
                $info["slug"] = $slug;
            }
            Config::createDir(CONFIG_DIR);
            file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", json_encode(array($this->routing, $this->slugs), JSON_PRETTY_PRINT));
            return $this;
        }

        /**
         * Método que devuelve el slug de un string dado
         * @param $text
         *
         * @return mixed|string
         */
        private function slugify($text)
        {
            // replace non letter or digits by -
            $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

            // trim
            $text = trim($text, '-');

            // transliterate
            if (function_exists('iconv'))
            {
                $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
            }

            // lowercase
            $text = strtolower($text);

            // remove unwanted characters
            $text = preg_replace('~[^-\w]+~', '', $text);

            if (empty($text))
            {
                return 'n-a';
            }

            return $text;
        }

        /**
         * Método que devuelve una ruta del framework
         *
         * @param string $slug
         * @param bool $absolute
         * @param $params
         *
         * @return mixed
         * @throws RouterException
         */
        public function getRoute($slug = '', $absolute = false, $params = null)
        {
            if(strlen($slug) == 0) return ($absolute) ? Request::getInstance()->getRootUrl() . '/'  : '/';
            if(!isset($this->slugs[$slug])) throw new RouterException("No existe la ruta especificada");
            $url = ($absolute) ? Request::getInstance()->getRootUrl() . $this->slugs[$slug] : $this->slugs[$slug];
            if(!empty($params)) foreach($params as $key => $value)
            {
                $url = str_replace("{".$key."}", $value, $url);
            }elseif(!empty($this->routing[$this->slugs[$slug]]["default"]))
            {
                $url = ($absolute) ? Request::getInstance()->getRootUrl() . $this->routing[$this->slugs[$slug]]["default"] : $this->routing[$this->slugs[$slug]]["default"];
            }
            return $url;
        }

        /**
         * Método que devuelve las rutas de administración
         * @return array
         */
        public function getAdminRoutes()
        {
            $routes = array();
            foreach($this->routing as $route => $params)
            {
                if(preg_match('/^\/admin(\/|$)/', $route))
                {
                    if(preg_match('/^PSFS/', $params["class"]))
                    {
                        $profile = "superadmin";
                    }else{
                        $profile = "admin";
                    }
                    if(!empty($params["default"]) && $params["visible"]) $routes[$profile][] = $params["slug"];
                }
            }
            asort($routes["superadmin"]);
            if(isset($routes["admin"])) asort($routes["admin"]);
            return $routes;
        }

        /**
         * Método que devuelve le controlador del admin
         * @return Admin
         */
        public function getAdmin()
        {
            return Admin::getInstance();
        }

        /**
         * Método que extrae los dominios
         * @return mixed
         */
        public function getDomains()
        {
            return $this->domains;
        }

        /**
         * Método que extrae el controller a invocar
         * @param $action
         * @return mixed
         */
        protected function getClassToCall($action)
        {
            $class = (method_exists($action["class"], "getInstance")) ? $action["class"]::getInstance() : new $action["class"];
            if(null !== $class && method_exists($class, "init")) {
                $class->init();
            }
            return $class;
        }
    }
