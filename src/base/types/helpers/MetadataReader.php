<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\MetadataAttributeContract;
use PSFS\base\types\helpers\attributes\VarType;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionType;

class MetadataReader
{
    private static array $legacyFallbackLogs = [];

    /**
     * @param string $tag
     * @param string|null $doc
     * @param mixed $default
     * @param ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector
     * @return mixed
     */
    public static function getTagValue(
        string $tag,
        ?string $doc = '',
        mixed $default = null,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        if (self::attributesEnabled()) {
            $value = self::readFromAttributes($tag, $reflector);
            if (null !== $value) {
                return $value;
            }
            if (!empty($doc)) {
                self::logLegacyFallback('annotation_' . $tag);
            }
        }
        return self::readFromDoc($tag, $doc, $default);
    }

    public static function hasInjectable(?ReflectionProperty $property, ?string $doc = ''): bool
    {
        $injectable = self::resolveInjectableDefinition($property, $doc);
        return $injectable['isInjectable'];
    }

    public static function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string
    {
        if (self::attributesEnabled() && null !== $property) {
            $injectable = self::resolveInjectableDefinition($property, $doc ?: '');
            if ($injectable['source'] === 'attribute' && is_string($injectable['class'])) {
                return $injectable['class'];
            }
            $attr = $property->getAttributes(VarType::class);
            if (!empty($attr)) {
                $instance = $attr[0]->newInstance();
                return $instance->value;
            }
            $propertyType = self::extractPropertyType($property->getType());
            if (null !== $propertyType) {
                return $propertyType;
            }
            if (!empty($doc)) {
                self::logLegacyFallback('annotation_var');
            }
        }
        return self::readVarTypeFromDoc($doc ?: '');
    }

    /**
     * @return array{
     *     isInjectable:bool,
     *     class:?string,
     *     singleton:bool,
     *     required:bool,
     *     source:?string
     * }
     */
    public static function resolveInjectableDefinition(?ReflectionProperty $property, ?string $doc = ''): array
    {
        $definition = [
            'isInjectable' => false,
            'class' => null,
            'singleton' => true,
            'required' => true,
            'source' => null,
        ];
        $doc = $doc ?: '';

        if (null !== $property && self::attributesEnabled()) {
            $attrs = $property->getAttributes(Injectable::class);
            if (!empty($attrs)) {
                $attribute = $attrs[0]->newInstance();
                return [
                    'isInjectable' => true,
                    'class' => $attribute->class,
                    'singleton' => $attribute->singleton,
                    'required' => $attribute->required,
                    'source' => 'attribute',
                ];
            }
            if ($doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1) {
                self::logLegacyFallback('annotation_injectable');
            }
        }

        if ($doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1) {
            $className = self::readVarTypeFromDoc($doc);
            return [
                'isInjectable' => $className !== null && trim($className) !== '',
                'class' => $className,
                'singleton' => true,
                'required' => true,
                'source' => 'annotation',
            ];
        }

        return $definition;
    }

    public static function getLegacyFallbackLogs(): array
    {
        return array_keys(self::$legacyFallbackLogs);
    }

    public static function clearLegacyFallbackLogs(): void
    {
        self::$legacyFallbackLogs = [];
    }

    private static function attributesEnabled(): bool
    {
        return (bool)Config::getParam('metadata.attributes.enabled', false);
    }

    private static function readFromAttributes(
        string $tag,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        if (null === $reflector) {
            return null;
        }
        $normalizedTag = strtolower($tag);
        foreach ($reflector->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if (!$instance instanceof MetadataAttributeContract) {
                continue;
            }
            if (strtolower($instance::tag()) === $normalizedTag) {
                return $instance->resolve();
            }
        }
        return null;
    }

    private static function readFromDoc(string $tag, ?string $doc, mixed $default = null): mixed
    {
        if (null === $doc || '' === $doc) {
            return $default;
        }
        return match ($tag) {
            'http' => self::readHttpMethodFromDoc($doc, $default),
            'visible' => self::readVisibleFromDoc($doc),
            'cache' => (bool)self::readFromDocValue('cache', $doc, $default ?? false),
            default => self::readFromDocValue($tag, $doc, $default),
        };
    }

    private static function readFromDocValue(string $needle, string $doc, mixed $default): mixed
    {
        preg_match('/@' . preg_quote($needle, '/') . '\ (.*)(\n|\r)/im', $doc, $matches);
        return !empty($matches) ? $matches[1] : $default;
    }

    private static function readVarTypeFromDoc(string $doc): ?string
    {
        $type = null;
        if (preg_match(InjectorHelper::VAR_PATTERN, $doc, $matches) === 1) {
            list(, $type) = $matches;
        }
        return $type;
    }

    private static function extractPropertyType(?ReflectionType $type): ?string
    {
        if (null === $type || method_exists($type, 'isBuiltin') && $type->isBuiltin()) {
            return null;
        }
        $name = method_exists($type, 'getName') ? $type->getName() : null;
        if (null === $name || '' === $name) {
            return null;
        }
        return str_starts_with($name, '\\') ? $name : '\\' . $name;
    }

    private static function logLegacyFallback(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackLogs)) {
            return;
        }
        self::$legacyFallbackLogs[$context] = true;
        Logger::log('[LegacyMetadata] ' . $context, LOG_NOTICE);
    }

    private static function readHttpMethodFromDoc(string $doc, mixed $default = null): string
    {
        preg_match('/@(GET|POST|PUT|DELETE|HEAD|PATCH)(\n|\r)/i', $doc, $routeMethod);
        return !empty($routeMethod) ? strtoupper($routeMethod[1]) : ($default ?? 'ALL');
    }

    private static function readVisibleFromDoc(string $doc): bool
    {
        preg_match('/@visible\ (.*)(\n|\r)/im', $doc, $matches);
        $value = !empty($matches) ? $matches[1] : '';
        return !str_contains((string)$value, 'false');
    }
}
