<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\SingletonTrait;

/**
 * Class Singleton
 * @package PSFS\base
 */
class Singleton
{
    use SingletonTrait;
    /**
     * @var bool Flag that indicated if the class is already loaded
     */
    protected $loaded = false;

    public function __construct()
    {
        Logger::log(get_class($this) . ' constructor invoked');
        $this->init();
    }

    /**
     * Magic setter
     * @param $variable
     * @param $value
     */
    public function __set($variable, $value)
    {
        if (property_exists(get_class($this), $variable)) {
            $this->$variable = $value;
        }
    }

    /**
     * Magic getter
     * @param string $variable
     * @return $mixed
     */
    public function __get($variable)
    {
        return property_exists(get_class($this), $variable) ? $this->$variable : null;
    }

    /**
     * Método que devuelve si una clase está isntanciada correctamente
     * @return bool
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * Método que configura como cargada una clase
     * @param bool $loaded
     */
    public function setLoaded($loaded = true)
    {
        $this->loaded = $loaded;
    }

    /**
     * HELPERS
     */

    /**
     * Método que extrae el nombre de la clase
     * @return string
     */
    public function getShortName()
    {
        $reflector = new \ReflectionClass(get_class($this));
        return $reflector->getShortName();
    }

    /**
     * Dependency injector service invoker
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @return $this
     */
    public function load($variable, $singleton = true, $classNameSpace = null)
    {
        $calledClass = get_called_class();
        try {
            $instance = InjectorHelper::constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
            $setter = "set" . ucfirst($variable);
            if (method_exists($calledClass, $setter)) {
                $this->$setter($instance);
            } else {
                $this->$variable = $instance;
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage() . ': ' . $e->getFile() . ' [' . $e->getLine() . ']', LOG_ERR);
        }
        return $this;
    }

    /**
     * Método que inyecta automáticamente las dependencias en la clase
     */
    public function init()
    {
        if (!$this->isLoaded()) {
            $filename = sha1(get_class($this));
            $cacheFilename = "reflections" . DIRECTORY_SEPARATOR . substr($filename, 0, 2) . DIRECTORY_SEPARATOR . substr($filename, 2, 2) . DIRECTORY_SEPARATOR . $filename . ".json";
            /** @var \PSFS\base\Cache $cacheService */
            $cacheService = Cache::getInstance();
            /** @var \PSFS\base\config\Config $configService */
            $configService = Config::getInstance();
            $properties = $cacheService->getDataFromFile($cacheFilename, Cache::JSON);
            if (true === $configService->getDebugMode() || null === $properties) {
                $properties = InjectorHelper::getClassProperties(get_class($this));
                $cacheService->storeData($cacheFilename, $properties, Cache::JSON);
            }
            /** @var \ReflectionProperty $property */
            if (!empty($properties) && is_array($properties)) foreach ($properties as $property => $class) {
                $this->load($property, true, $class);
            }
            $this->setLoaded();
        } else {
            Logger::log(get_class($this) . ' already loaded', LOG_INFO);
        }
    }
}
