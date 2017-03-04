<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\RequestHelper;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\SingletonTrait;
use PSFS\controller\base\Admin;
use PSFS\services\AdminServices;
use Symfony\Component\Finder\Finder;


/**
 * Class Router
 * @package PSFS
 */
class Router
{

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
     * @var bool headersSent
     */
    protected $headersSent = false;
    /**
     * @var int
     */
    protected $cacheType = Cache::JSON;

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
    public function init()
    {
        if(Cache::canUseMemcache()) {
            $this->cacheType = Cache::MEMCACHE;
        }
        list($this->routing, $this->slugs) = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", $this->cacheType, TRUE);
        $this->domains = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", $this->cacheType, TRUE);
        if (empty($this->routing) || Config::getInstance()->getDebugMode()) {
            $this->debugLoad();
        }
    }

    /**
     * Load routes and domains and store them
     */
    private function debugLoad() {
        Logger::log('Begin routes load', LOG_DEBUG);
        $this->hydrateRouting();
        $this->simpatize();
        $this->checkExternalModules(false);
        Logger::log('End routes load', LOG_DEBUG);
    }

    /**
     * Método que deriva un error HTTP de página no encontrada
     *
     * @param \Exception $e
     *
     * @return string HTML
     */
    public function httpNotFound(\Exception $e = NULL)
    {
        Logger::log('Throw not found exception');
        if (NULL === $e) {
            Logger::log('Not found page throwed without previous exception', LOG_WARNING);
            $e = new \Exception(_('Page not found'), 404);
        }
        $template = Template::getInstance()->setStatus($e->getCode());
        if (preg_match('/json/i', Request::getInstance()->getServer('CONTENT_TYPE'))) {
            return $template->output(json_encode(array(
                "success" => FALSE,
                "error" => $e->getMessage(),
            )), 'application/json');
        } else {
            return $template->render('error.html.twig', array(
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'error_page' => TRUE,
            ));
        }
    }

    /**
     * Método que devuelve las rutas
     * @return string|null
     */
    public function getSlugs()
    {
        return $this->slugs;
    }

    /**
     * @return mixed
     */
    public function getRoutes() {
        return $this->routing;
    }

    /**
     * Method that extract all routes in the platform
     * @return array
     */
    public function getAllRoutes()
    {
        $routes = [];
        foreach ($this->getRoutes() as $path => $route) {
            if (array_key_exists('slug', $route)) {
                $routes[$route['slug']] = $path;
            }
        }
        return $routes;
    }

    /**
     * Método que calcula el objeto a enrutar
     *
     * @param string|null $route
     *
     * @throws \Exception
     * @return string HTML
     */
    public function execute($route)
    {
        Logger::log('Executing the request');
        try {
            //Check CORS for requests
            RequestHelper::checkCORS();
            // Checks restricted access
            SecurityHelper::checkRestrictedAccess($route);
            //Search action and execute
            $this->searchAction($route);
        } catch (AccessDeniedException $e) {
            Logger::log(_('Solicitamos credenciales de acceso a zona restringida'));
            return Admin::staticAdminLogon($route);
        } catch (RouterException $r) {
            Logger::log($r->getMessage(), LOG_WARNING);
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }

        throw new RouterException(_("Página no encontrada"), 404);
    }

    /**
     * Método que busca el componente que ejecuta la ruta
     *
     * @param string $route
     *
     * @throws \PSFS\base\exception\RouterException
     */
    protected function searchAction($route)
    {
        Logger::log('Searching action to execute: ' . $route, LOG_INFO);
        //Revisamos si tenemos la ruta registrada
        $parts = parse_url($route);
        $path = (array_key_exists('path', $parts)) ? $parts['path'] : $route;
        $httpRequest = Request::getInstance()->getMethod();
        foreach ($this->routing as $pattern => $action) {
            list($httpMethod, $routePattern) = RouterHelper::extractHttpRoute($pattern);
            $matched = RouterHelper::matchRoutePattern($routePattern, $path);
            if ($matched && ($httpMethod === "ALL" || $httpRequest === $httpMethod) && RouterHelper::compareSlashes($routePattern, $path)) {
                $get = RouterHelper::extractComponents($route, $routePattern);
                /** @var $class \PSFS\base\types\Controller */
                $class = RouterHelper::getClassToCall($action);
                try {
                    $this->executeCachedRoute($route, $action, $class, $get);
                } catch (\Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
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
     * Method that check if the proyect has sub project to include
     * @param boolean $hydrateRoute
     */
    private function checkExternalModules($hydrateRoute = true)
    {
        $externalModules = Config::getParam('modules.extend');
        if (null !== $externalModules) {
            $externalModules = explode(',', $externalModules);
            foreach ($externalModules as &$module) {
                $module = preg_replace('/(\\\|\/)/', DIRECTORY_SEPARATOR, $module);
                $externalModulePath = VENDOR_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'src';
                if (file_exists($externalModulePath)) {
                    $externalModule = $this->finder->directories()->in($externalModulePath)->depth(0);
                    if (!empty($externalModule)) {
                        foreach ($externalModule as $modulePath) {
                            $extModule = $modulePath->getBasename();
                            $moduleAutoloader = realpath($externalModulePath . DIRECTORY_SEPARATOR . $extModule . DIRECTORY_SEPARATOR . 'autoload.php');
                            if (file_exists($moduleAutoloader)) {
                                @include $moduleAutoloader;
                                if ($hydrateRoute) {
                                    $this->routing = $this->inspectDir($externalModulePath . DIRECTORY_SEPARATOR . $extModule, '\\' . $extModule, $this->routing);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Method that gather all the routes in the project
     */
    private function generateRouting()
    {
        $base = SOURCE_DIR;
        $modulesPath = realpath(CORE_DIR);
        $this->routing = $this->inspectDir($base, "PSFS", array());
        if (file_exists($modulesPath)) {
            $modules = $this->finder->directories()->in($modulesPath)->depth(0);
            foreach ($modules as $modulePath) {
                $module = $modulePath->getBasename();
                $this->routing = $this->inspectDir($modulesPath . DIRECTORY_SEPARATOR . $module, $module, $this->routing);
            }
        }
        $this->checkExternalModules();
        $this->cache->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", $this->domains, Cache::JSON, TRUE);
    }

    /**
     * Método que regenera el fichero de rutas
     * @throws ConfigException
     */
    public function hydrateRouting()
    {
        $this->generateRouting();
        $home = Config::getInstance()->get('home_action');
        if (NULL !== $home || $home !== '') {
            $home_params = NULL;
            foreach ($this->routing as $pattern => $params) {
                list($method, $route) = RouterHelper::extractHttpRoute($pattern);
                if (preg_match("/" . preg_quote($route, "/") . "$/i", "/" . $home)) {
                    $home_params = $params;
                }
            }
            if (NULL !== $home_params) {
                $this->routing['/'] = $home_params;
            }
        }
    }

    /**
     * Método que inspecciona los directorios en busca de clases que registren rutas
     *
     * @param string $origen
     * @param string $namespace
     * @param array $routing
     *
     * @return array
     * @throws ConfigException
     */
    private function inspectDir($origen, $namespace = 'PSFS', $routing = [])
    {
        $files = $this->finder->files()->in($origen)->path('/(controller|api)/i')->depth(1)->name("*.php");
        foreach ($files as $file) {
            $filename = str_replace("/", '\\', str_replace($origen, '', $file->getPathname()));
            $routing = $this->addRouting($namespace . str_replace('.php', '', $filename), $routing, $namespace);
        }
        $this->finder = new Finder();

        return $routing;
    }

    /**
     * Checks that a namespace exists
     * @param string $namespace
     * @return bool
     */
    public static function exists($namespace)
    {
        return (class_exists($namespace) || interface_exists($namespace) || trait_exists($namespace));
    }

    /**
     * Método que añade nuevas rutas al array de referencia
     *
     * @param string $namespace
     * @param array $routing
     * @param string $module
     *
     * @return array
     * @throws ConfigException
     */
    private function addRouting($namespace, &$routing, $module = 'PSFS')
    {
        if (self::exists($namespace)) {
            $reflection = new \ReflectionClass($namespace);
            if (FALSE === $reflection->isAbstract() && FALSE === $reflection->isInterface()) {
                $this->extractDomain($reflection);
                $classComments = $reflection->getDocComment();
                preg_match('/@api\ (.*)\n/im', $classComments, $apiPath);
                $api = '';
                if (count($apiPath)) {
                    $api = array_key_exists(1, $apiPath) ? $apiPath[1] : $api;
                }
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    if (preg_match('/@route\ /i', $method->getDocComment())) {
                        list($route, $info) = RouterHelper::extractRouteInfo($method, str_replace('\\', '', $api), str_replace('\\', '', $module));

                        if (null !== $route && null !== $info) {
                            $info['class'] = $namespace;
                            $routing[$route] = $info;
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
    protected function extractDomain(\ReflectionClass $class)
    {
        //Calculamos los dominios para las plantillas
        if ($class->hasConstant("DOMAIN") && !$class->isAbstract()) {
            if (!$this->domains) {
                $this->domains = [];
            }
            $domain = "@" . $class->getConstant("DOMAIN") . "/";
            if (!array_key_exists($domain, $this->domains)) {
                $this->domains[$domain] = RouterHelper::extractDomainInfo($class, $domain);
            }
        }

        return $this;
    }

    /**
     * Método que genera las urls amigables para usar dentro del framework
     * @return Router
     */
    public function simpatize()
    {
        $translationFileName = "translations" . DIRECTORY_SEPARATOR . "routes_translations.php";
        $absoluteTranslationFileName = CACHE_DIR . DIRECTORY_SEPARATOR . $translationFileName;
        $this->generateSlugs($absoluteTranslationFileName);
        GeneratorHelper::createDir(CONFIG_DIR);
        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", array($this->routing, $this->slugs), Cache::JSON, TRUE);

        return $this;
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
    public function getRoute($slug = '', $absolute = FALSE, $params = [])
    {
        if (strlen($slug) === 0) {
            return ($absolute) ? Request::getInstance()->getRootUrl() . '/' : '/';
        }
        if (!is_array($this->slugs) || !array_key_exists($slug, $this->slugs)) {
            throw new RouterException(_("No existe la ruta especificada"));
        }
        $url = ($absolute) ? Request::getInstance()->getRootUrl() . $this->slugs[$slug] : $this->slugs[$slug];
        if (!empty($params)) foreach ($params as $key => $value) {
            $url = str_replace("{" . $key . "}", $value, $url);
        } elseif (!empty($this->routing[$this->slugs[$slug]]["default"])) {
            $url = ($absolute) ? Request::getInstance()->getRootUrl() . $this->routing[$this->slugs[$slug]]["default"] : $this->routing[$this->slugs[$slug]]["default"];
        }

        return preg_replace('/(GET|POST|PUT|DELETE|ALL)\#\|\#/', '', $url);
    }

    /**
     * Método que devuelve las rutas de administración
     * @deprecated
     * @return array
     */
    public function getAdminRoutes()
    {
        return AdminHelper::getAdminRoutes($this->routing);
    }

    /**
     * Método que devuelve le controlador del admin
     * @deprecated
     * @return Admin
     */
    public function getAdmin()
    {
        return Admin::getInstance();
    }

    /**
     * Método que extrae los dominios
     * @return array
     */
    public function getDomains()
    {
        return $this->domains ?: [];
    }

    /**
     * Método que ejecuta una acción del framework y revisa si lo tenemos cacheado ya o no
     *
     * @param string $route
     * @param array|null $action
     * @param types\Controller $class
     * @param array $params
     */
    protected function executeCachedRoute($route, $action, $class, $params = NULL)
    {
        Logger::log('Executing route ' . $route, LOG_INFO);
        Security::getInstance()->setSessionKey("__CACHE__", $action);
        $cache = Cache::needCache();
        $execute = TRUE;
        if (FALSE !== $cache && Config::getInstance()->getDebugMode() === FALSE) {
            $cacheDataName = $this->cache->getRequestCacheHash();
            $tmpDir = substr($cacheDataName, 0, 2) . DIRECTORY_SEPARATOR . substr($cacheDataName, 2, 2) . DIRECTORY_SEPARATOR;
            $cachedData = $this->cache->readFromCache("json" . DIRECTORY_SEPARATOR . $tmpDir . $cacheDataName,
                $cache, function () {
                });
            if (NULL !== $cachedData) {
                $headers = $this->cache->readFromCache("json" . DIRECTORY_SEPARATOR . $tmpDir . $cacheDataName . ".headers",
                    $cache, function () {
                    }, Cache::JSON);
                Template::getInstance()->renderCache($cachedData, $headers);
                $execute = FALSE;
            }
        }
        if ($execute) {
            Logger::log(_('Start executing action'), LOG_DEBUG);
            if (false === call_user_func_array(array($class, $action['method']), $params)) {
                Logger::log(_('An error ocurred trying to execute the action'), LOG_ERR, [error_get_last()]);
            }
        }
    }

    /**
     * Parse slugs to create translations
     *
     * @param string $absoluteTranslationFileName
     */
    private function generateSlugs($absoluteTranslationFileName)
    {
        $translations = I18nHelper::generateTranslationsFile($absoluteTranslationFileName);
        foreach ($this->routing as $key => &$info) {
            $keyParts = $key;
            if (FALSE === strstr("#|#", $key)) {
                $keyParts = explode("#|#", $key);
                $keyParts = array_key_exists(1, $keyParts) ? $keyParts[1] : '';
            }
            $slug = RouterHelper::slugify($keyParts);
            if (NULL !== $slug && !array_key_exists($slug, $translations)) {
                $translations[$slug] = $key;
                file_put_contents($absoluteTranslationFileName, "\$translations[\"{$slug}\"] = _(\"{$slug}\");\n", FILE_APPEND);
            }
            $this->slugs[$slug] = $key;
            $info["slug"] = $slug;
        }
    }

}
