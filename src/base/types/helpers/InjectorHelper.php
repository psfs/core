<?php

namespace PSFS\base\types\helpers;

use InvalidArgumentException;
use PSFS\base\Logger;
use PSFS\base\Router;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * @package PSFS\base\types\helpers
 */
class InjectorHelper
{
    const INJECTABLE_PATTERN = '/@(?:\x49\x6e\x79\x65\x63\x74\x61\x62\x6c\x65|Injectable|autoload|autowired)/im';
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
            $doc = $property->getDocComment() ?: '';
            $instanceType = self::extractVarType($doc, $property);
            if (null !== $instanceType) {
                $isRequired = self::checkIsRequired($doc, $property);
                $label = self::getLabel($doc, $property);
                $values = self::getValues($doc, $property);
                $variables[$property->getName()] = self::buildVariableDefinition($instanceType, $isRequired, $label);
                if (!empty($values)) {
                    $variables[$property->getName()]['enum'] = $values;
                }
            }
        }
        return $variables;
    }

    /**
     * @param ReflectionClass $reflector
     * @param integer $type
     * @param string $pattern
     * @return array
     */
    public static function extractProperties(
        ReflectionClass $reflector,
        $type = ReflectionProperty::IS_PROTECTED,
        $pattern = self::INJECTABLE_PATTERN
    ) {
        if ($pattern === self::INJECTABLE_PATTERN) {
            return self::extractInjectableProperties($reflector, $type);
        }

        $properties = [];
        foreach ($reflector->getProperties($type) as $property) {
            $doc = $property->getDocComment() ?: '';
            if (preg_match($pattern, $doc) === 1) {
                $instanceType = self::extractVarType($doc, $property);
                if (null !== $instanceType) {
                    $properties[$property->getName()] = $instanceType;
                }
            }
        }
        return $properties;
    }

    private static function extractInjectableProperties(ReflectionClass $reflector, int $type): array
    {
        $properties = [];
        foreach ($reflector->getProperties() as $property) {
            $doc = $property->getDocComment() ?: '';
            $injectable = MetadataReader::resolveInjectableDefinition($property, $doc);
            if ($injectable['isInjectable'] !== true) {
                continue;
            }
            self::assertInjectableVisibility($reflector, $property);
            if (!self::matchesPropertyVisibility($property, $type)) {
                continue;
            }
            $instanceType = is_string($injectable['class']) ? trim($injectable['class']) : '';
            if ($instanceType === '') {
                throw new InvalidArgumentException(sprintf(
                    '[Injectable] Missing dependency class for %s::$%s',
                    $reflector->getName(),
                    $property->getName()
                ));
            }
            $properties[$property->getName()] = $instanceType;
        }
        return $properties;
    }

    private static function assertInjectableVisibility(ReflectionClass $reflector, ReflectionProperty $property): void
    {
        if (!$property->isProtected()) {
            throw new InvalidArgumentException(sprintf(
                '[Injectable] %s::$%s must be protected',
                $reflector->getName(),
                $property->getName()
            ));
        }
    }

    private static function matchesPropertyVisibility(ReflectionProperty $property, int $type): bool
    {
        return match ($type) {
            ReflectionProperty::IS_PUBLIC => $property->isPublic(),
            ReflectionProperty::IS_PROTECTED => $property->isProtected(),
            ReflectionProperty::IS_PRIVATE => $property->isPrivate(),
            default => ($property->getModifiers() & $type) !== 0,
        };
    }

    /**
     * @param $doc
     * @return null|string
     */
    public static function extractVarType($doc, ReflectionProperty $property = null)
    {
        return MetadataReader::extractVarType($property, $doc ?: '');
    }

    /**
     * @param $doc
     * @return bool
     */
    public static function checkIsRequired($doc, ReflectionProperty $property = null)
    {
        if (null !== $property) {
            $required = MetadataReader::getTagValue('required', $doc ?: '', null, $property);
            if (null !== $required) {
                return (bool)$required;
            }
        }
        return preg_match('/@required/', $doc ?: '', $matches) === 1 && (bool)count($matches);
    }

    /**
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
    public static function getLabel($doc, ReflectionProperty $property = null)
    {
        return t(AnnotationHelper::extractReflectionLabel($doc ?: '', $property));
    }

    /**
     * @param $doc
     * @return null|array
     */
    public static function getValues($doc, ReflectionProperty $property = null)
    {
        $values = AnnotationHelper::extractFromDoc('values', $doc ?: '', '', $property);
        if (is_array($values)) {
            return $values;
        }
        return false !== strpos($values, '|') ? explode('|', $values) : $values;
    }

    /**
     * @param $doc
     * @return null|string
     */
    public static function getDefaultValue($doc, ReflectionProperty $property = null)
    {
        return AnnotationHelper::extractFromDoc('default', $doc ?: '', null, $property);
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
        $varInstanceType = (null === $classNameSpace) ? InjectorHelper::extractVarType(
            $property->getDocComment(),
            $property
        ) : $classNameSpace;
        if (true === $singleton && method_exists($varInstanceType, 'getInstance')) {
            $instance = $varInstanceType::getInstance();
        } else {
            $instance = new $varInstanceType();
        }
        return $instance;
    }

    /**
     * @param string $calledClass
     * @param string $propertyName
     * @param mixed $cachedDefinition
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     * @throws ReflectionException
     */
    public static function resolveInjectableRuntimeDefinition(
        string $calledClass,
        string $propertyName,
        mixed $cachedDefinition = null
    ): array {
        $reflector = new ReflectionClass($calledClass);
        if (!$reflector->hasProperty($propertyName)) {
            return self::normalizeCachedInjectableDefinition($cachedDefinition);
        }

        $property = $reflector->getProperty($propertyName);
        $doc = $property->getDocComment() ?: '';
        $definition = MetadataReader::resolveInjectableDefinition($property, $doc);
        if (($definition['isInjectable'] ?? false) === true) {
            return $definition;
        }

        return self::normalizeCachedInjectableDefinition($cachedDefinition);
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

    /**
     * @param mixed $cachedDefinition
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     */
    private static function normalizeCachedInjectableDefinition(mixed $cachedDefinition): array
    {
        if (is_string($cachedDefinition) && trim($cachedDefinition) !== '') {
            return [
                'isInjectable' => true,
                'class' => trim($cachedDefinition),
                'singleton' => true,
                'required' => true,
                'source' => 'cache',
            ];
        }

        if (is_array($cachedDefinition) && is_string($cachedDefinition['class'] ?? null)) {
            return [
                'isInjectable' => true,
                'class' => trim((string)$cachedDefinition['class']),
                'singleton' => array_key_exists('singleton', $cachedDefinition)
                    ? (bool)$cachedDefinition['singleton']
                    : true,
                'required' => array_key_exists('required', $cachedDefinition)
                    ? (bool)$cachedDefinition['required']
                    : true,
                'source' => 'cache',
            ];
        }

        return [
            'isInjectable' => false,
            'class' => null,
            'singleton' => true,
            'required' => true,
            'source' => null,
        ];
    }

    /**
     * @param string $instanceType
     * @param bool $required
     * @param mixed $label
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private static function buildVariableDefinition(string $instanceType, bool $required, mixed $label): array
    {
        [$normalizedType, $isArray] = self::normalizeInstanceType($instanceType);
        if ($normalizedType === '\\DateTime' || !Router::exists($normalizedType)) {
            list($type, $format) = DocumentorHelper::translateSwaggerFormats($normalizedType);
            return [
                'type' => $type,
                'format' => $format,
                'required' => $required,
                'description' => $label,
            ];
        }

        $instance = new ReflectionClass($normalizedType);
        return [
            'is_array' => $isArray,
            'class' => $normalizedType,
            'type' => $instance->getShortName(),
            'properties' => self::extractVariables($instance),
        ];
    }

    /**
     * @return array{string,bool}
     */
    private static function normalizeInstanceType(string $instanceType): array
    {
        $isArray = (bool)preg_match('/\[\]$/', $instanceType);
        if ($isArray) {
            $instanceType = str_replace('[]', '', $instanceType);
        }
        return [$instanceType, $isArray];
    }
}
