<?php

namespace PSFS\base\types\helpers;

use InvalidArgumentException;
use PSFS\base\Logger;
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
            $definition = self::buildVariableDefinition($property);
            if (is_array($definition)) {
                $variables[$property->getName()] = $definition;
            }
        }
        return $variables;
    }

    private static function buildVariableDefinition(ReflectionProperty $property): ?array
    {
        $doc = $property->getDocComment() ?: '';
        $instanceType = self::extractVarType($doc, $property);
        if (null === $instanceType) {
            return null;
        }

        $definition = InjectorDefinitionHelper::buildVariableDefinition(
            $instanceType,
            self::checkIsRequired($doc, $property),
            self::getLabel($doc, $property)
        );
        $values = self::getValues($doc, $property);
        if (!empty($values)) {
            $definition['enum'] = $values;
        }

        return $definition;
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

        return self::extractPatternMatchedProperties($reflector, (int)$type, (string)$pattern);
    }

    private static function extractPatternMatchedProperties(ReflectionClass $reflector, int $type, string $pattern): array
    {
        $properties = [];
        foreach ($reflector->getProperties($type) as $property) {
            $doc = self::propertyDoc($property);
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
        return MetadataReader::extractVarType($property, self::docValue($doc));
    }

    /**
     * @param $doc
     * @return bool
     */
    public static function checkIsRequired($doc, ReflectionProperty $property = null)
    {
        $doc = self::docValue($doc);
        if (null !== $property) {
            $required = MetadataReader::getTagValue('required', $doc, null, $property);
            if (null !== $required) {
                return (bool)$required;
            }
        }
        return preg_match('/@required/', $doc, $matches) === 1 && (bool)count($matches);
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
        return t(AnnotationHelper::extractReflectionLabel(self::docValue($doc), $property));
    }

    /**
     * @param $doc
     * @return null|array
     */
    public static function getValues($doc, ReflectionProperty $property = null)
    {
        $values = AnnotationHelper::extractFromDoc('values', self::docValue($doc), '', $property);
        if (is_array($values)) {
            return $values;
        }
        if (is_string($values)) {
            return self::splitDelimitedValues($values);
        }
        return $values;
    }

    /**
     * @param $doc
     * @return null|string
     */
    public static function getDefaultValue($doc, ReflectionProperty $property = null)
    {
        return AnnotationHelper::extractFromDoc('default', self::docValue($doc), null, $property);
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

        return self::buildInjectableInstance((string)$varInstanceType, (bool)$singleton);
    }

    private static function buildInjectableInstance(string $instanceType, bool $singleton): mixed
    {
        if ($singleton && method_exists($instanceType, 'getInstance')) {
            return $instanceType::getInstance();
        }

        return new $instanceType();
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
            return InjectorDefinitionHelper::normalizeCachedDefinition($cachedDefinition);
        }

        $property = $reflector->getProperty($propertyName);
        $doc = $property->getDocComment() ?: '';
        $definition = MetadataReader::resolveInjectableDefinition($property, $doc);
        if (($definition['isInjectable'] ?? false) === true) {
            return $definition;
        }

        return InjectorDefinitionHelper::normalizeCachedDefinition($cachedDefinition);
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

    private static function propertyDoc(ReflectionProperty $property): string
    {
        return (string)($property->getDocComment() ?: '');
    }

    private static function docValue(mixed $doc): string
    {
        return is_string($doc) ? $doc : '';
    }

    /**
     * @return string|array<int, string>
     */
    private static function splitDelimitedValues(string $values): string|array
    {
        if (str_contains($values, '|')) {
            return explode('|', $values);
        }
        return $values;
    }

}
