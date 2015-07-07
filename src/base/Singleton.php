<?php

namespace PSFS\base;

/**
 * Class Singleton
 * @package PSFS\base
 */
class Singleton
{
    /**
     * @var Singleton cached reference to singleton instance
     */
    protected static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return $this
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(self::$instance[$class]) || !self::$instance[$class] instanceof $class) {
            self::$instance[$class] = new $class(func_get_args());
            self::$instance[$class]->init();
        }
        return self::$instance[$class];
    }

    /**
     * is not allowed to call from outside: private!
     *
     */
    private function __construct(){
        $this->init();
    }

    /**
     * prevent the instance from being cloned
     *
     * @return void
     */
    private function __clone(){}

    /**
     * prevent from being unserialized
     *
     * @return void
     */
    private function __wakeup(){}

    /**
     * Magic setter
     * @param $variable
     * @param $value
     */
    public function __set($variable, $value) {
        $this->$variable = $value;
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
            $setter = "set" . ucfirst($variable);
            if(method_exists($calledClass, $setter)) {
                $instance = $this->constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
                $this->$setter($instance);
            }else {
                $instance = $this->constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass);
                $this->$variable = $instance;
            }
        }catch(\Exception $e) {
            Logger::getInstance()->errorLog($e->getMessage());
        }
        return $this;
    }

    /**
     * Método que inyecta automáticamente las dependencias en la clase
     */
    public function init() {
        $properties = $this->getClassProperties();
        /** @var \ReflectionProperty $property */
        if(!empty($properties)) foreach($properties as $property => $class) {
            $this->load($property, true, $class);
        }
    }

    /**
     * Método que extrae todas las propiedades inyectables de una clase
     * @param null $class
     * @return array
     */
    private function getClassProperties($class = null) {
        $properties = array();
        if(empty($class)) $class = get_class($this);
        $selfReflector = new \ReflectionClass($class);
        if(null !== $selfReflector->getParentClass()) {
            $properties = $this->getClassProperties($selfReflector->getParentClass()->getName());
        }
        foreach($selfReflector->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
            $doc = $property->getDocComment();
            if(preg_match('/@Inyectable/im', $doc)) {
                $properties[$property->getName()] = $this->extractVarType($property->getDocComment());
            }
        }
        return $properties;
    }

    /**
     * Método que extrae el tipo de instancia de la variable
     * @param $doc
     * @return null
     */
    private function extractVarType($doc) {
        $type = null;
        if (preg_match('/@var\s+([^\s]+)/', $doc, $matches)) {
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
        $varInstanceType = (empty($classNameSpace)) ? $this->extractVarType($property->getDocComment()) : $classNameSpace;
        if (method_exists($varInstanceType, "getInstance") && true === $singleton) {
            $instance = $varInstanceType::getInstance();
            return $instance;
        } else {
            $instance = new $varInstanceType();
            return $instance;
        }
    }
}
