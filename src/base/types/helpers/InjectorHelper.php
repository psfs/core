<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Logger;
use PSFS\base\Router;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Class InjectorHelper
 * @package PSFS\base\types\helpers
 */
class InjectorHelper
{
    const INJECTABLE_PATTERN = '/@(Inyectable|Injectable|autoload|autowired)/im';
    const VAR_PATTERN = '/@var\s+([^\s]+)/im';

    /**
     * @param ReflectionClass $reflector
     * @return array
     * @throws ReflectionException
     */
    public static function extractVariables(ReflectionClass $reflector)
    {
        $variables = [];
        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $instanceType = self::extractVarType($property->getDocComment());
            if (null !== $instanceType) {
                $isRequired = self::checkIsRequired($property->getDocComment());
                $label = self::getLabel($property->getDocComment());
                $values = self::getValues($property->getDocComment());
                $isArray = (bool)preg_match('/\[\]$/', $instanceType);
                if($isArray) {
                    $instanceType = str_replace('[]', '', $instanceType);
                }
                if($instanceType === '\\DateTime' || !Router::exists($instanceType)) {
                    list($type, $format) = DocumentorHelper::translateSwaggerFormats($instanceType);
                    $variables[$property->getName()] = [
                        'type' => $type,
                        'format' => $format,
                        'required' => $isRequired,
                        'description' => $label,
                    ];
                } else {
                    $instance = new ReflectionClass($instanceType);
                    $variables[$property->getName()] = [
                        'is_array' => $isArray,
                        'class' => $instanceType,
                        'type' => $instance->getShortName(),
                        'properties' => self::extractVariables($instance),
                    ];
                }
                if(!empty($values)){
                    $variables[$property->getName()]['enum'] = $values;
                }
            }
        }
        return $variables;
    }

    /**
     * Method that extract the properties of a Class
     * @param ReflectionClass $reflector
     * @param integer $type
     * @param string $pattern
     * @return array
     */
    public static function extractProperties(ReflectionClass $reflector, $type = ReflectionProperty::IS_PROTECTED, $pattern = self::INJECTABLE_PATTERN)
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
        if (false !== preg_match(self::VAR_PATTERN, $doc, $matches)) {
            list(, $type) = $matches;
        }
        return $type;
    }

    /**
     * Method extract if a variable is required
     * @param $doc
     * @return bool
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
     * @return bool
     */
    public static function checkIsVisible($doc)
    {
        return AnnotationHelper::extractReflectionVisibility($doc);
    }

    /**
     * @param $doc
     * @return null|string
     */
    public static function getLabel($doc) {
        return t(AnnotationHelper::extractReflectionLabel($doc));
    }

    /**
     * @param $doc
     * @return null|array
     */
    public static function getValues($doc) {
        // Extract description
        $values = AnnotationHelper::extractFromDoc('values', $doc, '');
        return false !== strpos($values, '|') ? explode('|', $values) : $values;
    }

    /**
     * @param $doc
     * @return null|string
     */
    public static function getDefaultValue($doc) {
        return AnnotationHelper::extractFromDoc('default', $doc);
    }

    /**
     * @param $variable
     * @param $singleton
     * @param $classNameSpace
     * @param $calledClass
     * @return mixed
     * @throws ReflectionException
     */
    public static function constructInjectableInstance($variable, $singleton, $classNameSpace, $calledClass)
    {
        Logger::log('Create inyectable instance for ' . $classNameSpace);
        $reflector = new ReflectionClass($calledClass);
        $property = $reflector->getProperty($variable);
        $varInstanceType = (null === $classNameSpace) ? InjectorHelper::extractVarType($property->getDocComment()) : $classNameSpace;
        if (true === $singleton && method_exists($varInstanceType, 'getInstance')) {
            $instance = $varInstanceType::getInstance();
        } else {
            $instance = new $varInstanceType();
        }
        return $instance;
    }

    /**
     * @param $class
     * @return array
     * @throws ReflectionException
     */
    public static function getClassProperties($class)
    {
        $properties = [];
        Logger::log('Extracting annotations properties from class ' . $class);
        $selfReflector = new ReflectionClass($class);
        if (false !== $selfReflector->getParentClass()) {
            $properties = self::getClassProperties($selfReflector->getParentClass()->getName());
        }
        $properties = array_merge($properties, self::extractProperties($selfReflector));
        return $properties;
    }
}
