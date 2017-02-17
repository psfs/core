<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Logger;

/**
 * Class InjectorHelper
 * @package PSFS\base\types\helpers
 */
class InjectorHelper
{

    /**
     * @param \ReflectionClass $reflector
     * @return array
     */
    public static function extractVariables(\ReflectionClass $reflector)
    {
        $variables = [];
        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $instanceType = self::extractVarType($property->getDocComment());
            if (null !== $instanceType) {
                $variables[$property->getName()] = $instanceType;
            }
        }
        return $variables;
    }

    /**
     * Method that extract the properties of a Class
     * @param \ReflectionClass $reflector
     * @param integer $type
     * @return array
     */
    public static function extractProperties(\ReflectionClass $reflector, $type = \ReflectionProperty::IS_PROTECTED)
    {
        $properties = [];
        foreach ($reflector->getProperties($type) as $property) {
            $doc = $property->getDocComment();
            if (preg_match('/@(Inyectable|Injectable|autoload|autowired)/im', $doc)) {
                $instanceType = self::extractVarType($property->getDocComment());
                if (null !== $instanceType) {
                    $properties[$property->getName()] = $instanceType;
                }
            }
        }
        return $properties;
    }

    /**
     * Method that extract the instance of the class
     * @param $doc
     * @return null|string
     */
    public static function extractVarType($doc)
    {
        $type = null;
        if (false !== preg_match('/@var\s+([^\s]+)/', $doc, $matches)) {
            list(, $type) = $matches;
        }
        return $type;
    }

    /**
     * Create the dependency injected
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @param string $calledClass
     * @return mixed
     */
    public static function constructInyectableInstance($variable, $singleton, $classNameSpace, $calledClass)
    {
        Logger::log('Create inyectable instance for ' . $classNameSpace);
        $reflector = new \ReflectionClass($calledClass);
        $property = $reflector->getProperty($variable);
        $varInstanceType = (null === $classNameSpace) ? InjectorHelper::extractVarType($property->getDocComment()) : $classNameSpace;
        if (true === $singleton && method_exists($varInstanceType, "getInstance")) {
            $instance = $varInstanceType::getInstance();
        } else {
            $instance = new $varInstanceType();
        }
        return $instance;
    }

    /**
     * Method that extract all the properties of a class
     * @param string $class
     * @return array
     */
    public static function getClassProperties($class)
    {
        $properties = [];
        Logger::log('Extracting annotations properties from class ' . $class);
        $selfReflector = new \ReflectionClass($class);
        if (false !== $selfReflector->getParentClass()) {
            $properties = self::getClassProperties($selfReflector->getParentClass()->getName());
        }
        $properties = array_merge($properties, self::extractProperties($selfReflector));
        return $properties;
    }
}