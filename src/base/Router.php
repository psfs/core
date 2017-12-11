<?php
namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\exception\AccessDeniedException;
use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\helpers\AdminHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\controller\base\Admin;
use PSFS\services\AdminServices;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Router
 * @package PSFS
 */
class Router
{
    use SingletonTrait;

    /**
     * @var array
     */
    protected $routing = [];
    /**
     * @var array
     */
    protected $slugs = [];
    /**
     * @var array
     */
    private $domains = [];
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
     * @throws ConfigException
     */
    public function __construct()
    {
        $this->finder = new Finder();
        $this->cache = Cache::getInstance();
        $this->init();
    }

    /**
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function init()
    {
        list($this->routing, $this->slugs) = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", $this->cacheType, TRUE);
        if (empty($this->routing) || Config::getInstance()->getDebugMode()) {
            $this->debugLoad();
        } else {
            $this->domains = $this->cache->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json", $this->cacheType, TRUE);
        }
        $this->checkExternalModules(false);
        $this->setLoaded();
    }

    /**
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    private function debugLoad() {
        Logger::log('Begin routes load');
        $this->hydrateRouting();
        $this->simpatize();
        Logger::log('End routes load');
    }

    /**
     * @param \Exception|NULL $e
     * @param bool $isJson
     * @return string
     * @throws RouterException
     */
    public function httpNotFound(\Exception $e = NULL, $isJson = false)
    {
        Logger::log('Throw not found exception');
        if (NULL === $e) {
            Logger::log('Not found page thrown without previous exception', LOG_WARNING);
            $e = new \Exception(_('Page not found'), 404);
        }
        $template = Template::getInstance()->setStatus($e->getCode());
        if ($isJson || false !== stripos(Request::getInstance()->getServer('CONTENT_TYPE'), 'json')) {
            $response = new JsonResponse(null, false, 0, 0, $e->getMessage());
            return $template->output(json_encode($response), 'application/json');
        }

        $not_found_route = Config::getParam('route.404');
        if(null !== $not_found_route) {
            Request::getInstance()->redirect($this->getRoute($not_found_route, true));
        } else {
            return $template->render('error.html.twig', array(
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'error_page' => TRUE,
            ));
        }
    }

    /**
     * @return array
     */
    public function getSlugs()
    {
        return $this->slugs;
    }

    /**
     * @return array
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
     * @param string|null $route
     *
     * @throws \Exception
     * @return string HTML
     */
    public function execute($route)
    {
        Logger::log('Executing the request');
        try {
            //Search action and execute
            $this->searchAction($route);
        } catch (AccessDeniedException $e) {
            Logger::log(_('Solicitamos credenciales de acceso a zona restringida'), LOG_WARNING, ['file' => $e->getFile() . '[' . $e->getLine() . ']']);
            return Admin::staticAdminLogon($route);
        } catch (RouterException $r) {
            Logger::log($r->getMessage(), LOG_WARNING);
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
            throw $e;
        }

        throw new RouterException(_('Página no encontrada'), 404);
    }

    /**
     * @param $route
     * @throws AccessDeniedException
     * @throws AdminCredentialsException
     * @throws RouterException
     * @throws \Exception
     */
    protected function searchAction($route)
    {
        Logger::log('Searching action to execute: ' . $route, LOG_INFO);
        //Revisamos si tenemos la ruta registrada
        $parts = parse_url($route);
        $path = array_key_exists('path', $parts) ? $parts['path'] : $route;
        $httpRequest = Request::getInstance()->getMethod();
        foreach ($this->routing as $pattern => $action) {
            list($httpMethod, $routePattern) = RouterHelper::extractHttpRoute($pattern);
            $matched = RouterHelper::matchRoutePattern($routePattern, $path);
            if ($matched && ($httpMethod === 'ALL' || $httpRequest === $httpMethod) && RouterHelper::compareSlashes($routePattern, $path)) {
                // Checks restricted access
                SecurityHelper::checkRestrictedAccess($route);
                $get = RouterHelper::extractComponents($route, $routePattern);
                /** @var $class \PSFS\base\types\Controller */
                $class = RouterHelper::getClassToCall($action);
                try {
                    if($this->checkRequirements($action, $get)) {
                        $this->executeCachedRoute($route, $action, $class, $get);
                    } else {
                        throw new RouterException(_('La ruta no es válida'), 400);
                    }
                } catch (\Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
                    throw $e;
                }
            }
        }
        throw new RouterException(_('Ruta no encontrada'));
    }

    /**
     * @param array $action
     * @param array $params
     * @return bool
     */
    private function checkRequirements(array $action, $params = []) {
        if(!empty($params) && !empty($action['requirements'])) {
            $checked = 0;
            foreach(array_keys($params) as $key) {
                if(in_array($key, $action['requirements'], true)) {
                    $checked++;
                }
            }
            $valid = count($action['requirements']) === $checked;
        } else {
            $valid = true;
        }
        return $valid;
    }

    /**
     * @return string HTML
     */
    protected function sentAuthHeader()
    {
        return AdminServices::getInstance()->setAdminHeaders();
    }

    /**
     * @return string|null
     */
    private function getExternalModules() {
        $externalModules = Config::getParam('modules.extend', '');
        $externalModules .= ',psfs/auth';
        return $externalModules;
    }

    /**
     * @param boolean $hydrateRoute
     */
    private function checkExternalModules($hydrateRoute = true)
    {
        $externalModules = $this->getExternalModules();
        if ('' !== $externalModules) {
            $externalModules = explode(',', $externalModules);
            foreach ($externalModules as &$module) {
                $module = $this->loadExternalModule($hydrateRoute, $module);
            }
        }
    }

    /**
     * @throws exception\GeneratorException
     * @throws ConfigException
     * @throws \InvalidArgumentException
     */
    private function generateRouting()
    {
        $base = SOURCE_DIR;
        $modulesPath = realpath(CORE_DIR);
        $this->routing = $this->inspectDir($base, 'PSFS', array());
        $this->checkExternalModules();
        if (file_exists($modulesPath)) {
            $modules = $this->finder->directories()->in($modulesPath)->depth(0);
            if(is_array($modules)) {
                foreach ($modules as $modulePath) {
                    $module = $modulePath->getBasename();
                    $this->routing = $this->inspectDir($modulesPath . DIRECTORY_SEPARATOR . $module, $module, $this->routing);
                }
            }
        }
        $this->cache->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', $this->domains, Cache::JSON, TRUE);
    }

    /**
     * @throws exception\GeneratorException
     * @throws ConfigException
     * @throws \InvalidArgumentException
     */
    public function hydrateRouting()
    {
        $this->generateRouting();
        $home = Config::getInstance()->get('home.action');
        if (NULL !== $home || $home !== '') {
            $home_params = NULL;
            foreach ($this->routing as $pattern => $params) {
                list($method, $route) = RouterHelper::extractHttpRoute($pattern);
                if (preg_match('/' . preg_quote($route, '/') . '$/i', '/' . $home)) {
                    $home_params = $params;
                }
            }
            if (NULL !== $home_params) {
                $this->routing['/'] = $home_params;
            }
        }
    }

    /**
     * @param string $origen
     * @param string $namespace
     * @param array $routing
     * @return array
     * @throws ConfigException
     * @throws \InvalidArgumentException
     */
    private function inspectDir($origen, $namespace = 'PSFS', $routing = [])
    {
        $files = $this->finder->files()->in($origen)->path('/(controller|api)/i')->depth(1)->name('*.php');
        if(is_array($files)) {
            foreach ($files as $file) {
                $filename = str_replace('/', '\\', str_replace($origen, '', $file->getPathname()));
                $routing = $this->addRouting($namespace . str_replace('.php', '', $filename), $routing, $namespace);
            }
        }
        $this->finder = new Finder();

        return $routing;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public static function exists($namespace)
    {
        return (class_exists($namespace) || interface_exists($namespace) || trait_exists($namespace));
    }

    /**
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
            if(I18nHelper::checkI18Class($namespace)) {
                return $routing;
            }
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
     *
     * @param \ReflectionClass $class
     *
     * @return Router
     * @throws ConfigException
     */
    protected function extractDomain(\ReflectionClass $class)
    {
        //Calculamos los dominios para las plantillas
        if ($class->hasConstant('DOMAIN') && !$class->isAbstract()) {
            if (!$this->domains) {
                $this->domains = [];
            }
            $domain = '@' . $class->getConstant('DOMAIN') . '/';
            if (!array_key_exists($domain, $this->domains)) {
                $this->domains[$domain] = RouterHelper::extractDomainInfo($class, $domain);
            }
        }

        return $this;
    }

    /**
     * @return $this
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function simpatize()
    {
        $translationFileName = 'translations' . DIRECTORY_SEPARATOR . 'routes_translations.php';
        $absoluteTranslationFileName = CACHE_DIR . DIRECTORY_SEPARATOR . $translationFileName;
        $this->generateSlugs($absoluteTranslationFileName);
        GeneratorHelper::createDir(CONFIG_DIR);
        Cache::getInstance()->storeData(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json', array($this->routing, $this->slugs), Cache::JSON, TRUE);

        return $this;
    }

    /**
     * @param string $slug
     * @param boolean $absolute
     * @param array $params
     *
     * @return string|null
     * @throws RouterException
     */
    public function getRoute($slug = '', $absolute = FALSE, array $params = [])
    {
        if ('' === $slug) {
            return $absolute ? Request::getInstance()->getRootUrl() . '/' : '/';
        }
        if (!is_array($this->slugs) || !array_key_exists($slug, $this->slugs)) {
            throw new RouterException(_('No existe la ruta especificada'));
        }
        $url = $absolute ? Request::getInstance()->getRootUrl() . $this->slugs[$slug] : $this->slugs[$slug];
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        } elseif (!empty($this->routing[$this->slugs[$slug]]['default'])) {
            $url = $absolute ? Request::getInstance()->getRootUrl() . $this->routing[$this->slugs[$slug]]['default'] : $this->routing[$this->slugs[$slug]]['default'];
        }

        return preg_replace('/(GET|POST|PUT|DELETE|ALL)\#\|\#/', '', $url);
    }

    /**
     * @deprecated
     * @return array
     */
    public function getAdminRoutes()
    {
        return AdminHelper::getAdminRoutes($this->routing);
    }

    /**
     * @deprecated
     * @return Admin
     */
    public function getAdmin()
    {
        return Admin::getInstance();
    }

    /**
     * @return array
     */
    public function getDomains()
    {
        return $this->domains ?: [];
    }

    /**
     * @param string $route
     * @param array $action
     * @param string $class
     * @param array $params
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    protected function executeCachedRoute($route, $action, $class, $params = NULL)
    {
        Logger::log('Executing route ' . $route, LOG_INFO);
        $action['params'] = array_merge($action['params'], $params, Request::getInstance()->getQueryParams());
        Security::getInstance()->setSessionKey(Cache::CACHE_SESSION_VAR, $action);
        $cache = Cache::needCache();
        $execute = TRUE;
        if (FALSE !== $cache && $action['http'] === 'GET' && Config::getParam('debug') === FALSE) {
            list($path, $cacheDataName) = $this->cache->getRequestCacheHash();
            $cachedData = $this->cache->readFromCache('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName,
                $cache);
            if (NULL !== $cachedData) {
                $headers = $this->cache->readFromCache('json' . DIRECTORY_SEPARATOR . $path . $cacheDataName . '.headers',
                    $cache, null, Cache::JSON);
                Template::getInstance()->renderCache($cachedData, $headers);
                $execute = FALSE;
            }
        }
        if ($execute) {
            Logger::log(_('Start executing action'));
            if (false === call_user_func_array(array($class, $action['method']), $params)) {
                Logger::log(_('An error occurred trying to execute the action'), LOG_ERR, [error_get_last()]);
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
            $keyParts = explode('#|#', $key);
            $keyParts = array_key_exists(1, $keyParts) ? $keyParts[1] : $keyParts[0];
            $slug = RouterHelper::slugify($keyParts);
            if (NULL !== $slug && !array_key_exists($slug, $translations)) {
                $translations[$slug] = $info['label'];
                file_put_contents($absoluteTranslationFileName, "\$translations[\"{$slug}\"] = _(\"{$info['label']}\");\n", FILE_APPEND);
            }
            $this->slugs[$slug] = $key;
            $info['slug'] = $slug;
        }
    }

    /**
     * @param bool $hydrateRoute
     * @param $modulePath
     * @param $externalModulePath
     */
    private function loadExternalAutoloader($hydrateRoute, SplFileInfo $modulePath, $externalModulePath)
    {
        $extModule = $modulePath->getBasename();
        $moduleAutoloader = realpath($externalModulePath . DIRECTORY_SEPARATOR . $extModule . DIRECTORY_SEPARATOR . 'autoload.php');
        if(file_exists($moduleAutoloader)) {
            include_once $moduleAutoloader;
            if ($hydrateRoute) {
                $this->routing = $this->inspectDir($externalModulePath . DIRECTORY_SEPARATOR . $extModule, '\\' . $extModule, $this->routing);
            }
        }
    }

    /**
     * @param $hydrateRoute
     * @param $module
     * @return mixed
     */
    private function loadExternalModule($hydrateRoute, $module)
    {
        try {
            $module = preg_replace('/(\\\|\/)/', DIRECTORY_SEPARATOR, $module);
            $externalModulePath = VENDOR_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'src';
            $externalModule = $this->finder->directories()->in($externalModulePath)->depth(0);
            if(is_array($externalModule)) {
                foreach ($externalModule as $modulePath) {
                    $this->loadExternalAutoloader($hydrateRoute, $modulePath, $externalModulePath);
                }
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
            $module = null;
        }
        return $module;
    }

}
