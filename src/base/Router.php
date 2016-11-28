<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\helpers\AdminHelper;
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
        if (!file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json") || Config::getInstance()->getDebugMode()) {
            $this->hydrateRouting();
            $this->simpatize();
        } else {
            list($this->routing, $this->slugs) = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", Cache::JSON, TRUE);
            $this->domains = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", Cache::JSON, TRUE);
        }
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
        $template = Template::getInstance()->setStatus($e->getCode());
        if (preg_match('/json/i', Request::getInstance()->getServer('CONTENT_TYPE'))) {
            return $template->output(json_encode(array(
                "success" => FALSE,
                "error" => $e->getMessage(),
            )), 'application/json');
        } else {
            if (NULL === $e) {
                Logger::log('Not found page throwed without previous exception', LOG_WARNING);
                $e = new \Exception(_('Page not found'), 404);
            }

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
     * Method that extract all routes in the platform
     * @return array
     */
    public function getAllRoutes() {
        $routes = [];
        foreach($this->routing as $path => $route) {
            if(array_key_exists('slug', $route)) {
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
            if(null === RouterHelper::checkDefaultRoute($route)) {
                Logger::log($r->getMessage(), LOG_WARNING);
                throw $r;
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }

        return $this->httpNotFound();
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
     * Method that gather all the routes in the project
     */
    private function generateRouting()
    {
        $base = SOURCE_DIR;
        $modules = realpath(CORE_DIR);
        $this->routing = $this->inspectDir($base, "PSFS", array());
        if (file_exists($modules)) {
            $module = "";
            if(file_exists($modules . DIRECTORY_SEPARATOR . 'module.json')) {
                $mod_cfg = json_decode(file_get_contents($modules . DIRECTORY_SEPARATOR . 'module.json'), true);
                $module = $mod_cfg['module'];
            }
            $this->routing = $this->inspectDir($modules, $module, $this->routing);
        }
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
    private function inspectDir($origen, $namespace = 'PSFS', $routing)
    {
        $files = $this->finder->files()->in($origen)->path('/(controller|api)/i')->name("*.php");
        foreach ($files as $file) {
            $filename = str_replace("/", '\\', str_replace($origen, '', $file->getPathname()));
            $routing = $this->addRouting($namespace . str_replace('.php', '', $filename), $routing);
        }
        $this->finder = new Finder();

        return $routing;
    }

    /**
     * Checks that a namespace exists
     * @param string $namespace
     * @return bool
     */
    public static function exists($namespace) {
        return (class_exists($namespace) || interface_exists($namespace) || trait_exists($namespace));
    }

    /**
     * Método que añade nuevas rutas al array de referencia
     *
     * @param string $namespace
     * @param array $routing
     *
     * @return array
     * @throws ConfigException
     */
    private function addRouting($namespace, &$routing)
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
                    list($route, $info) = RouterHelper::extractRouteInfo($method, $api);
                    if(null !== $route && null !== $info) {
                        $info['class'] = $namespace;
                        $routing[$route] = $info;
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
        if ($class->hasConstant("DOMAIN")) {
            $domain = "@" . $class->getConstant("DOMAIN") . "/";
            $this->domains[$domain] = RouterHelper::extractDomainInfo($class, $domain);
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
        Config::createDir(CONFIG_DIR);
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
        if (NULL === $slug || !array_key_exists($slug, $this->slugs)) {
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
     * @return array
     */
    public function getAdminRoutes()
    {
        return AdminHelper::getAdminRoutes($this->routing);
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
            $cachedData = $this->cache->readFromCache("templates" . DIRECTORY_SEPARATOR . $cacheDataName,
                $cache, function () {});
            if (NULL !== $cachedData) {
                $headers = $this->cache->readFromCache("templates" . DIRECTORY_SEPARATOR . $cacheDataName . ".headers",
                    $cache, function () {}, Cache::JSON);
                Template::getInstance()->renderCache($cachedData, $headers);
                $execute = FALSE;
            }
        }
        if ($execute) {
            call_user_func_array(array($class, $action['method']), $params);
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
