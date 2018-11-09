<?php
namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class Singleton
 * @package PSFS\base
 */
class Singleton
{
    use SingletonTrait;

    /**
     * Singleton constructor.
     * @throws \Exception
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function __construct()
    {
        Logger::log(static::class . ' constructor invoked');
        $this->init();
    }

    /**
     * @param string $variable
     * @param mixed $value
     */
    public function __set($variable, $value)
    {
        if ($this->__isset($variable)) {
            $this->$variable = $value;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return property_exists(get_class($this), $name);
    }

    /**
     * @param string $variable
     * @return mixed
     */
    public function __get($variable)
    {
        return $this->__isset($variable) ? $this->$variable : null;
    }

    /**
     * HELPERS
     */

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function getShortName()
    {
        $reflector = new \ReflectionClass(get_class($this));
        return $reflector->getShortName();
    }

    /**
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @return $this
     * @throws \Exception
     */
    public function load($variable, $singleton = true, $classNameSpace = null)
    {
        $calledClass = static::class;
        try {
            $instance = InjectorHelper::constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
            $setter = 'set' . ucfirst($variable);
            if (method_exists($calledClass, $setter)) {
                $this->$setter($instance);
            } else {
                $this->$variable = $instance;
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage() . ': ' . $e->getFile() . ' [' . $e->getLine() . ']', LOG_ERR);
            throw $e;
        }
        return $this;
    }

    /**
     * @throws \Exception
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function init()
    {
        if (!$this->isLoaded()) {
            $filename = sha1(get_class($this));
            $cacheFilename = 'reflections' . DIRECTORY_SEPARATOR . substr($filename, 0, 2) . DIRECTORY_SEPARATOR . substr($filename, 2, 2) . DIRECTORY_SEPARATOR . $filename . '.json';
            /** @var \PSFS\base\Cache $cacheService */
            $cacheService = Cache::getInstance();
            /** @var \PSFS\base\config\Config $configService */
            $configService = Config::getInstance();
            $cache = Cache::canUseMemcache() ? Cache::MEMCACHE : Cache::JSON;
            $properties = $cacheService->getDataFromFile($cacheFilename, $cache);
            if (!$properties || true === $configService->getDebugMode()) {
                $properties = InjectorHelper::getClassProperties(get_class($this));
                $cacheService->storeData($cacheFilename, $properties, $cache);
            }
            /** @var \ReflectionProperty $property */
            if (!empty($properties) && is_array($properties)) {
                foreach ($properties as $property => $class) {
                    $this->load($property, true, $class);
                }
            }
            $this->setLoaded();
        } else {
            Logger::log(get_class($this) . ' already loaded', LOG_INFO);
        }
    }
}
