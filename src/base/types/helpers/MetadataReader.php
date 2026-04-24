<?php

namespace PSFS\base\types\helpers;

use PSFS\base\types\helpers\metadata\MetadataEngine;
use PSFS\base\types\helpers\metadata\MetadataEngineInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class MetadataReader
{
    private static ?MetadataEngineInterface $engine = null;

    public static function getTagValue(
        string $tag,
        ?string $doc = '',
        mixed $default = null,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        return self::engine()->getTagValue($tag, $doc, $default, $reflector);
    }

    public static function hasDeprecated(
        ?ReflectionMethod $method = null,
        ?string $doc = ''
    ): bool {
        return self::engine()->hasDeprecated($method, $doc);
    }

    public static function extractPayload(
        string $defaultNamespace,
        ?ReflectionMethod $method = null,
        ?string $doc = ''
    ): string {
        return self::engine()->extractPayload($defaultNamespace, $method, $doc);
    }

    public static function extractReturnSpec(
        ?ReflectionMethod $method = null,
        ?string $doc = ''
    ): ?string {
        return self::engine()->extractReturnSpec($method, $doc);
    }

    public static function hasInjectable(?ReflectionProperty $property, ?string $doc = ''): bool
    {
        $injectable = self::resolveInjectableDefinition($property, $doc);
        return (bool)($injectable['isInjectable'] ?? false);
    }

    public static function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string
    {
        return self::engine()->extractVarType($property, $doc);
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolveInjectableDefinition(?ReflectionProperty $property, ?string $doc = ''): array
    {
        return self::engine()->resolveInjectableDefinition($property, $doc);
    }

    /**
     * @return array<int, string>
     */
    public static function getLegacyFallbackLogs(): array
    {
        $engine = self::engine();
        if ($engine instanceof MetadataEngine) {
            return $engine->getLegacyFallbackLogs();
        }
        return [];
    }

    public static function clearLegacyFallbackLogs(): void
    {
        $engine = self::engine();
        if ($engine instanceof MetadataEngine) {
            $engine->clearLegacyFallbackLogs();
        }
    }

    /**
     * @return array<string, int|float>
     */
    public static function getEngineStats(): array
    {
        return self::engine()->getStats();
    }

    public static function resetEngineCaches(): void
    {
        self::engine()->clearLocalCache();
    }

    private static function engine(): MetadataEngineInterface
    {
        if (!(self::$engine instanceof MetadataEngineInterface)) {
            self::$engine = new MetadataEngine();
        }
        return self::$engine;
    }
}
