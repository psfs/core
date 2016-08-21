<?php

namespace PSFS\base;

use PSFS\base\config\Config;
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
     * Dependency inyector service invoker
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @return $this
     */
    public function load($variable, $singleton = true, $classNameSpace = null)
    {
        $calledClass = get_called_class();
        try {
            $instance = $this->constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
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
            $cacheFilename = "reflections" . DIRECTORY_SEPARATOR . sha1(get_class($this)) . ".json";
            /** @var \PSFS\base\Cache $cacheService */
            $cacheService = Cache::getInstance();
            /** @var \PSFS\base\config\Config $configService */
            $configService = Config::getInstance();
            $properties = $cacheService->getDataFromFile($cacheFilename, Cache::JSON);
            if (true === $configService->getDebugMode() || null === $properties) {
                $properties = $this->getClassProperties();
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

    /**
     * Método que extrae todas las propiedades inyectables de una clase
     * @param null $class
     * @return array
     */
    private function getClassProperties($class = null)
    {
        $properties = array();
        if (null === $class) {
            $class = get_class($this);
        }
        Logger::log('Extracting annotations properties from class ' . $class);
        $selfReflector = new \ReflectionClass($class);
        if (false !== $selfReflector->getParentClass()) {
            $properties = $this->getClassProperties($selfReflector->getParentClass()->getName());
        }
        foreach ($selfReflector->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
            $doc = $property->getDocComment();
            if (preg_match('/@Inyectable/im', $doc)) {
                $instanceType = $this->extractVarType($property->getDocComment());
                if (null !== $instanceType) {
                    $properties[$property->getName()] = $instanceType;
                }
            }
        }
        return $properties;
    }

    /**
     * Método que extrae el tipo de instancia de la variable
     * @param $doc
     * @return null|string
     */
    private function extractVarType($doc)
    {
        $type = null;
        if (false !== preg_match('/@var\s+([^\s]+)/', $doc, $matches)) {
            list(, $type) = $matches;
        }
        return $type;
    }

    /**
     * Create the depecency injected
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @param string $calledClass
     * @return mixed
     */
    private function constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass)
    {
        Logger::log('Create inyectable instance for ' . $classNameSpace . ' into ' . get_class($this));
        $reflector = new \ReflectionClass($calledClass);
        $property = $reflector->getProperty($variable);
        $varInstanceType = (null === $classNameSpace) ? $this->extractVarType($property->getDocComment()) : $classNameSpace;
        if (true === $singleton && method_exists($varInstanceType, "getInstance")) {
            $instance = $varInstanceType::getInstance();
        } else {
            $instance = new $varInstanceType();
        }
        return $instance;
    }
}
