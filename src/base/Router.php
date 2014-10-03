<?php

    namespace PSFS\base;

    use PSFS\base\Singleton;
    use PSFS\base\config\Config;
    use PSFS\base\exception\ConfigException;
    use PSFS\base\config\AdminForm;
    use PSFS\base\Security;
    use PSFS\base\exception\RouterException;
    use PSFS\controller\Admin;
    use Symfony\Component\Finder\Finder;
    /**
     * Class Router
     * @package PSFS
     */
    class Router extends Singleton{

        protected $routing;
        protected $slugs;
        private $finder;
        private $controller;
        private $domains;

        function __construct()
        {
            $this->controller = new Admin();
            $this->finder = new Finder();
            if(Config::getInstance()->getDebugMode() || !file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json"))
            {
                $this->hydrateRouting();
                $this->simpatize();
            }else
            {
                list($this->routing, $this->slugs) = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json"), true);
                $this->domains = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json"), true);
            }
        }

        /**
         * Método que deriva un error HTTP de página no encontrada
         */
        public function httpNotFound(\Exception $e = null)
        {
            if(empty($e)) $e = new \Exception('Página no encontrada', 404);
            return Template::getInstance()->setStatus($e->getCode())->render('error.html.twig', array(
                'exception' => $e,
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
         *
         * @return bool
         */
        public function execute($route)
        {
            //Chequeamos si entramos en el admin
            if(preg_match('/^\/admin/i', $route))
            {
                if(!Security::getInstance()->checkAdmin())
                {
                    if("login" === Config::getInstance()->get("admin_login")) return $this->controller->adminLogin($route);
                    header('HTTP/1.1 401 Unauthorized');
                    header('WWW-Authenticate: Basic Realm="PSFS"');
                    echo _("Es necesario ser administrador para ver ésta zona");
                    exit();
                }
            }
            //Restricción de la web por contraseña
            if(!preg_match('/^\/(admin|setup\-admin)/i', $route) && null !== Config::getInstance()->get('restricted'))
            {
                if(!Security::getInstance()->checkAdmin())
                {
                    if("login" === Config::getInstance()->get("admin_login")) return $this->controller->adminLogin($route);
                    header('HTTP/1.1 401 Unauthorized');
                    header('WWW-Authenticate: Basic Realm="Zona Restringida"');
                    echo _("Espacio web restringido");
                    exit();
                }
            }

            //Revisamos si tenemos la ruta registrada
            foreach($this->routing as $pattern => $action)
            {
                $expr = preg_replace('/\{(.*)\}/', "###", $pattern);
                $expr = preg_quote($expr, "/");
                $expr = str_replace("###", "(.*)", $expr);
                if(preg_match("/^". $expr ."$/i", $route))
                {
                    $get = $this->extractComponents($route, $pattern);
                    /** @var $class PSFS\base\types\Controller */
                    $class = (method_exists($action["class"], "getInstance")) ? $action["class"]::getInstance() : new $action["class"];
                    try{

                        return call_user_func_array(array($class, $action["method"]), $get);
                    }catch(\Exception $e)
                    {
                        throw $e;
                    }
                }
            }

            if(preg_match('/\/$/', $route)) return $this->execute(substr($route, 0, strlen($route) -1));

            return false;
        }

        /**
         * Método que extrae de la url los parámetros REST
         * @param $route
         *
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
         */
        private function hydrateRouting()
        {
            $base = SOURCE_DIR;
            $modules = realpath(CORE_DIR);
            $this->routing = $this->inspectDir($base, "PSFS", array());
            $this->routing = $this->inspectDir($modules, "", $this->routing);
            file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", json_encode($this->domains, JSON_PRETTY_PRINT));
            $home = Config::getInstance()->get('home_action');
            if(!empty($home))
            {
                $home_params = null;
                foreach($this->routing as $pattern => $params)
                {
                    if(preg_match("/".preg_quote($pattern, "/")."$/i", "/".$home)) $home_params = $params;
                }
                if(!empty($home_params)) $this->routing['/'] = $home_params;
            }
        }

        /**
         * Método que inspecciona los directorios en busca de clases que registren rutas
         * @param $dir
         * @param $routing
         *
         * @return mixed
         */
        private function inspectDir($origen, $namespace = "PSFS", $routing)
        {
            $files = $this->finder->files()->in($origen)->path("/controller/i")->name("*.php");
            foreach($files as $file)
            {
                $filename = str_replace("/", '\\', str_replace($origen, '', $file->getPathname()));
                $routing = $this->addRouting($namespace .str_replace(".php", "", $filename), $routing);
            }
            $this->finder = new Finder();
            return $routing;
        }

        /**
         * Método que añade nuevas rutas al array de referencia
         * @param $routing
         *
         * @return mixed
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
                                if(!empty($parameters)) foreach($parameters as $param)
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
         * @param $class
         *
         * @return $this
         */
        protected function extractDomain($class)
        {
            //Calculamos los dominios para las plantillas
            if(!$class->hasConstant("DOMAIN")) throw new ConfigException(_("El controlador debe tener un dominio asignado"));
            $domain = "@" . $class->getConstant("DOMAIN") . "/";
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
                if(!file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . "translations")) @mkdir(CACHE_DIR . DIRECTORY_SEPARATOR . "translations", 0775, true);
                if(!file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php")) file_put_contents(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php", "<?php \$translations = array();\n");
                include(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php");
                if(!isset($translations[$slug])) file_put_contents(CACHE_DIR . DIRECTORY_SEPARATOR . "translations" . DIRECTORY_SEPARATOR . "routes_translations.php", "\$translations[\"{$slug}\"] = _(\"{$slug}\");\n", FILE_APPEND);
                $this->slugs[$slug] = $key;
                $info["slug"] = $slug;
            }
            if(!file_exists(CONFIG_DIR)) @mkdir(CONFIG_DIR, 0775);
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
         * @param $slug
         * @param $absolute
         * @param $params
         *
         * @return mixed
         * @throws \PSFS\base\exception\ConfigException
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
         * Método que devuelve le controladordel admin
         * @return Admin
         */
        public function getAdmin()
        {
            return $this->controller;
        }

        /**
         * Método que extrae los dominios
         * @return mixed
         */
        public function getDomains()
        {
            return $this->domains;
        }
    }