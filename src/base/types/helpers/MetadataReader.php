<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\attributes\Action;
use PSFS\base\types\helpers\attributes\Api;
use PSFS\base\types\helpers\attributes\Cacheable;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Required;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Values;
use PSFS\base\types\helpers\attributes\VarType;
use PSFS\base\types\helpers\attributes\Visible;
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
        if (self::attributesEnabled() && null !== $property) {
            if (count($property->getAttributes(Injectable::class)) > 0) {
                return true;
            }
            if (!empty($doc) && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc)) {
                self::logLegacyFallback('annotation_injectable');
            }
        }
        return !empty($doc) && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1;
    }

    public static function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string
    {
        if (self::attributesEnabled() && null !== $property) {
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
        $tag = strtolower($tag);
        return match ($tag) {
            'route' => self::readAttributeValue($reflector, Route::class),
            'api' => self::readAttributeValue($reflector, Api::class),
            'action' => self::readAttributeValue($reflector, Action::class),
            'label' => self::readAttributeValue($reflector, Label::class),
            'icon' => self::readAttributeValue($reflector, Icon::class),
            'visible' => self::readAttributeValue($reflector, Visible::class),
            'cache' => self::readAttributeValue($reflector, Cacheable::class),
            'http' => self::readAttributeValue($reflector, HttpMethod::class),
            'required' => self::readAttributeValue($reflector, Required::class),
            'values' => self::readAttributeValue($reflector, Values::class),
            'default' => self::readAttributeValue($reflector, DefaultValue::class),
            default => null,
        };
    }

    private static function readAttributeValue(
        ReflectionClass|ReflectionMethod|ReflectionProperty $reflector,
        string $attributeClass
    ): mixed {
        $attributes = $reflector->getAttributes($attributeClass);
        if (empty($attributes)) {
            return null;
        }
        $instance = $attributes[0]->newInstance();
        return $instance->value;
    }

    private static function readFromDoc(string $tag, ?string $doc, mixed $default = null): mixed
    {
        if (null === $doc || '' === $doc) {
            return $default;
        }
        if ($tag === 'http') {
            preg_match('/@(GET|POST|PUT|DELETE|HEAD|PATCH)(\n|\r)/i', $doc, $routeMethod);
            return !empty($routeMethod) ? strtoupper($routeMethod[1]) : ($default ?? 'ALL');
        }
        if ($tag === 'visible') {
            preg_match('/@visible\ (.*)(\n|\r)/im', $doc, $matches);
            $value = !empty($matches) ? $matches[1] : '';
            return !str_contains((string)$value, 'false');
        }
        if ($tag === 'cache') {
            return (bool)self::readFromDocValue('cache', $doc, $default ?? false);
        }

        return self::readFromDocValue($tag, $doc, $default);
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
}
