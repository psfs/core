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
     * @var bool flag que indica si la clase ha sido instanciada correctamente
     */
    protected $loaded = false;

    /**
     * is not allowed to call from outside: private!
     *
     */
    private function __construct() {
        $this->init();
    }

    /**
     * prevent the instance from being cloned
     *
     * @return void
     */
    private function __clone() {}

    /**
     * prevent from being unserialized
     *
     * @return void
     */
    private function __wakeup() {}

    /**
     * Magic setter
     * @param $variable
     * @param $value
     */
    public function __set($variable, $value) {
        $this->$variable = $value;
    }

    /**
     * Método que devuelve si una clase está isntanciada correctamente
     * @return bool
     */
    public function isLoaded() {
        return $this->loaded;
    }

    /**
     * Método que configura como cargada una clase
     * @param bool $loaded
     */
    public function setLoaded($loaded = true) {
        $this->loaded = $loaded;
    }

    /**
     * HELPERS
     */

    /**
     * Método que extrae el nombre de la clase
     * @return string
     */
    public function getShortName() {
        $reflector = new \ReflectionClass(get_class($this));
        return $reflector->getShortName();
    }

    /**
     * Servicio de inyección de dependencias
     * @param string $variable
     * @param bool $singleton
     * @return $this
     */
    public function load($variable, $singleton = true, $classNameSpace = null) {
        $calledClass = get_called_class();
        try {
            $instance = $this->constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
            $setter = "set".ucfirst($variable);
            if (method_exists($calledClass, $setter)) {
                $this->$setter($instance);
            } else {
                $this->$variable = $instance;
            }
        } catch (\Exception $e) {
            Logger::getInstance()->errorLog($e->getMessage());
        }
        return $this;
    }

    /**
     * Método que inyecta automáticamente las dependencias en la clase
     */
    public function init() {
        /** @var \PSFs\base\Logger $logService */
        $logService = Logger::getInstance(get_class($this));
        if (!$this->isLoaded()) {
            $cacheFilename = "reflections".DIRECTORY_SEPARATOR.sha1(get_class($this)).".json";
            /** @var \PSFS\base\Cache $cacheService */
            $cacheService = Cache::getInstance();
            /** @var \PSFS\base\config\Config $configService */
            $configService = Config::getInstance();
            $properties = $cacheService->getDataFromFile($cacheFilename, CACHE::JSON);
            if (true === $configService->getDebugMode() || null === $properties) {
                $properties = $this->getClassProperties();
                $cacheService->storeData($cacheFilename, $properties, Cache::JSON);
            }
            /** @var \ReflectionProperty $property */
            if (!empty($properties) && is_array($properties)) foreach ($properties as $property => $class) {
                $this->load($property, true, $class);
                $logService->debugLog("Propiedad ".$property." cargada con clase ".$class);
            }
            $this->setLoaded();
        }else {
            $logService->debugLog(get_class($this)." ya cargada");
        }
    }

    /**
     * Método que extrae todas las propiedades inyectables de una clase
     * @param null $class
     * @return array
     */
    private function getClassProperties($class = null) {
        $properties = array();
        if (null === $class) {
            $class = get_class($this);
        }
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
    private function extractVarType($doc) {
        $type = null;
        if (false !== preg_match('/@var\s+([^\s]+)/', $doc, $matches)) {
            list(, $type) = $matches;
        }
        return $type;
    }

    /**
     * @param $variable
     * @param $singleton
     * @param $classNameSpace
     * @param $calledClass
     * @return mixed
     */
    private function constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass)
    {
        $reflector = new \ReflectionClass($calledClass);
        $property = $reflector->getProperty($variable);
        $varInstanceType = (null === $classNameSpace) ? $this->extractVarType($property->getDocComment()) : $classNameSpace;
        if (true === $singleton && method_exists($varInstanceType, "getInstance")) {
            $instance = $varInstanceType::getInstance();
            return $instance;
        } else {
            $instance = new $varInstanceType();
            return $instance;
        }
    }
}
