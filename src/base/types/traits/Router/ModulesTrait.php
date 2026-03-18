<?php

namespace PSFS\base\types\traits\Router;

use Exception;
use InvalidArgumentException;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\types\helpers\AnnotationHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\RouterHelper;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @package PSFS\base\types\traits\Router
 */
trait ModulesTrait
{
    use SluggerTrait;

    /**
     * @Injectable
     * @var Finder
 */
    private $finder;
    /**
     * @var array
 */
    private $domains = [];

    /**
     *
 */
    public function initializeFinder()
    {
        $this->finder = new Finder();
    }

    /**
     * @param string $origen
     * @param string $namespace
     * @param array $routing
     * @return array
     * @throws ReflectionException
     * @throws ConfigException
     * @throws InvalidArgumentException
 */
    private function inspectDir($origen, $namespace = 'PSFS', $routing = [])
    {
        foreach ($this->iterateControllerFiles($origen) as $file) {
            if ($namespace !== Router::PSFS_BASE_NAMESPACE && method_exists($file, 'getRelativePathname')) {
                $filename = '\\' . str_replace('/', '\\', str_replace($origen, '', $file->getRelativePathname()));
            } else {
                $filename = str_replace('/', '\\', str_replace($origen, '', $file->getPathname()));
            }
            $routing = $this->addRouting($namespace . str_replace('.php', '', $filename), $routing, $namespace);
        }
        $this->initializeFinder();
        return $routing;
    }

    /**
     * @param string $namespace
     * @param array $routing
     * @param string $module
     * @return array
     * @throws ReflectionException
 */
    private function addRouting($namespace, &$routing, $module = Router::PSFS_BASE_NAMESPACE)
    {
        if (self::exists($namespace) && !I18nHelper::checkI18Class($namespace)) {
            $reflection = new ReflectionClass($namespace);
            if (false === $reflection->isAbstract() && FALSE === $reflection->isInterface()) {
                $this->extractDomain($reflection);
                $classComments = $reflection->getDocComment();
                $api = AnnotationHelper::extractApi($classComments ?: '', $reflection);
                foreach ($this->iterateRoutesFromClass($reflection, (string)$api, (string)$module, $namespace) as $routeData) {
                    list($route, $info) = $routeData;
                    $routing[$route] = $info;
                }
            }
        }

        return $routing;
    }

    /**
     *
     * @param ReflectionClass $class
     *
     * @return $this
     * @throws ConfigException
 */
    protected function extractDomain(ReflectionClass $class)
    {
        // Resolve template domains.
        if ($class->hasConstant('DOMAIN') && !$class->isAbstract()) {
            if (!is_array($this->domains)) {
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
     * @return array
 */
    public function getDomains()
    {
        return $this->domains ?: [];
    }

    /**
     * @param boolean $hydrateRoute
     * @param SplFileInfo $modulePath
     * @param string $externalModulePath
     * @param array $routing
     * @throws ReflectionException
 */
    private function loadExternalAutoloader($hydrateRoute, SplFileInfo $modulePath, $externalModulePath, &$routing = [])
    {
        $extModule = $modulePath->getBasename();
        $moduleAutoloader = realpath($externalModulePath . DIRECTORY_SEPARATOR . $extModule . DIRECTORY_SEPARATOR . 'autoload.php');
        if (file_exists($moduleAutoloader)) {
            include_once $moduleAutoloader;
            if ($hydrateRoute) {
                $routing = $this->inspectDir($externalModulePath . DIRECTORY_SEPARATOR . $extModule, '\\' . $extModule, $routing);
            }
        }
    }

    /**
     * @param boolean $hydrateRoute
     * @param string $module
     * @param array $routing
     * @return mixed
 */
    private function loadExternalModule($hydrateRoute, $module, &$routing = [])
    {
        $modulesToIgnore = explode(',', Config::getParam('hide.modules', ''));
        try {
            $module = preg_replace('/(\\\|\/)/', DIRECTORY_SEPARATOR, $module);
            $externalModulePath = VENDOR_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'src';
            if (file_exists($externalModulePath)) {
                $externalModule = $this->finder->directories()->in($externalModulePath)->depth(0);
                if ($externalModule->hasResults()) {
                    foreach ($externalModule->getIterator() as $modulePath) {
                        if (!in_array(strtoupper($modulePath->getRelativePathname()), $modulesToIgnore)) {
                            $this->loadExternalAutoloader($hydrateRoute, $modulePath, $externalModulePath, $routing);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
        }
    }

    /**
     * @param string $domainToCheck
     * @return bool
 */
    public function domainExists($domainToCheck)
    {
        $exists = false;
        $domains = array_keys($this->getDomains());
        foreach ($domains as $domain) {
            $cleanDomain = strtolower(str_replace(['@', '/', '\\'], '', $domain));
            if ($cleanDomain === strtolower($domainToCheck)) {
                $exists = true;
                break;
            }
        }
        return $exists;
    }

    /**
     * @return string|null
 */
    private function getExternalModules()
    {
        $externalModules = Config::getParam('modules.extend', '');
        $externalModules .= ',psfs/auth,psfs/nosql';
        return $externalModules;
    }

    /**
     * @param boolean $hydrateRoute
 */
    private function checkExternalModules($hydrateRoute = true)
    {
        $externalModules = $this->getExternalModules();
        $externalModules = explode(',', $externalModules);
        foreach ($externalModules as $module) {
            if (strlen($module)) {
                $this->loadExternalModule($hydrateRoute, $module, $this->routing);
            }
        }
    }

    /**
     * @param string $origin
     * @return \Generator
 */
    private function iterateControllerFiles(string $origin): \Generator
    {
        $files = $this->finder->files()->in($origin)->path('/(controller|api)/i')->depth('< 3')->name('*.php');
        if (!$files->hasResults()) {
            return;
        }
        foreach ($files->getIterator() as $file) {
            yield $file;
        }
    }

    /**
     * @param ReflectionClass $reflection
     * @param string $api
     * @param string $module
     * @param string $namespace
     * @return \Generator
     * @throws ReflectionException
 */
    private function iterateRoutesFromClass(ReflectionClass $reflection, string $api, string $module, string $namespace): \Generator
    {
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $route = AnnotationHelper::extractRoute($method->getDocComment() ?: '', $method);
            if (null === $route) {
                continue;
            }
            list($route, $info) = RouterHelper::extractRouteInfo($method, str_replace('\\', '', $api), str_replace('\\', '', $module));
            if (null !== $route && null !== $info) {
                $info['class'] = $namespace;
                yield [$route, $info];
            }
        }
    }

    
    protected function calculateRoutingFingerprint(): string
    {
        $seedParts = [];
        $scanTargets = [SOURCE_DIR];
        $modulesPath = realpath(CORE_DIR);
        if (is_string($modulesPath) && $modulesPath !== '') {
            $scanTargets[] = $modulesPath;
        }

        foreach ($scanTargets as $origin) {
            if (!is_dir($origin)) {
                continue;
            }
            $finder = new Finder();
            $files = $finder
                ->files()
                ->in($origin)
                ->path('/(controller|api)/i')
                ->depth('< 3')
                ->name('*.php');
            foreach ($files as $file) {
                $realPath = $file->getRealPath() ?: $file->getPathname();
                $seedParts[] = $realPath . '|' . $file->getMTime() . '|' . $file->getSize();
            }
        }

        sort($seedParts);
        return sha1(implode(';', $seedParts));
    }
}
