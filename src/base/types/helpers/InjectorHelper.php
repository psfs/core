<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Logger;
use PSFS\services\DocumentorService;

/**
 * Class InjectorHelper
 * @package PSFS\base\types\helpers
 */
class InjectorHelper
{
    const INJECTABLE_PATTERN = '/@(Inyectable|Injectable|autoload|autowired)/im';
    const VAR_PATTERN = '/@var /im';

    /**
     * @param \ReflectionClass $reflector
     * @return array
     */
    public static function extractVariables(\ReflectionClass $reflector)
    {
        $variables = [];
        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $instanceType = self::extractVarType($property->getDocComment());
            $isRequired = self::checkIsRequired($property->getDocComment());
            if (null !== $instanceType) {
                list($type, $format) = DocumentorHelper::translateSwaggerFormats($instanceType);
                $variables[$property->getName()] = [
                    'type' => $type,
                    'format' => $format,
                    'required' => $isRequired,
                ];
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
    public static function extractProperties(\ReflectionClass $reflector, $type = \ReflectionProperty::IS_PROTECTED, $pattern = self::INJECTABLE_PATTERN)
    {
        $properties = [];
        foreach ($reflector->getProperties($type) as $property) {
            $doc = $property->getDocComment();
            if (preg_match($pattern, $doc)) {
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
     * Method extract if a variable is required
     * @param $doc
     * @return null|string
     */
    public static function checkIsRequired($doc)
    {
        $required = false;
        if (false !== preg_match('/@required/', $doc, $matches)) {
            $required = (bool)count($matches);
        }
        return $required;
    }

    /**
     * Method extract if a class or variable is visible
     * @param $doc
     * @return null|string
     */
    public static function checkIsVisible($doc)
    {
        $visible = false;
        if (false !== preg_match('/@visible\s+([^\s]+)/', $doc, $matches)) {
            $visible = 'false' !== strtolower($matches[1]);
        }
        return $visible;
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