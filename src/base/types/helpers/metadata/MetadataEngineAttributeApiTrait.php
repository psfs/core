<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\exception\MetadataContractException;
use PSFS\base\Logger;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Public metadata accessors and attribute-backed resolution helpers.
 *
 * Requires attribute, configuration, bundle, and static fallback-log state from MetadataEngine.
 */
trait MetadataEngineAttributeApiTrait
{
    public function getTagValue(
        string $tag,
        ?string $doc = '',
        mixed $default = null,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        return $this->tagValueResolver()->getTagValue($tag, $doc ?? '', $default, $reflector);
    }

    public function hasDeprecated(?ReflectionMethod $method = null, ?string $doc = ''): bool
    {
        return $this->tagValueResolver()->hasDeprecated($method, $doc ?? '');
    }

    public function extractPayload(string $defaultNamespace, ?ReflectionMethod $method = null, ?string $doc = ''): string
    {
        return $this->definitionResolver()->extractPayload($defaultNamespace, $method, $doc ?? '');
    }

    public function extractReturnSpec(?ReflectionMethod $method = null, ?string $doc = ''): ?string
    {
        return $this->definitionResolver()->extractReturnSpec($method, $doc ?? '');
    }

    public function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string
    {
        $doc = $doc ?? '';
        return $this->definitionResolver()->extractVarType(
            $property,
            $doc,
            $property === null ? null : $this->attributeBundleBuilder()->propertyType($property->getType()),
            $property === null ? null : $this->resolveInjectableDefinition($property, $doc)
        );
    }

    public function resolveInjectableDefinition(?ReflectionProperty $property, ?string $doc = ''): array
    {
        return $this->definitionResolver()->resolveInjectableDefinition($property, $doc ?? '');
    }

    public function getClassMetadata(string $fqcn): ClassMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        return new ClassMetadata($fqcn, $bundle['class_tags'] ?? [], $bundle['signature'] ?? '');
    }

    public function getMethodMetadata(string $fqcn, string $method): MethodMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        $methodTags = $bundle['method_tags'][$method] ?? [];
        return new MethodMetadata($fqcn, $method, $methodTags, $bundle['signature'] ?? '');
    }

    public function getPropertyMetadata(string $fqcn, string $property): PropertyMetadata
    {
        $bundle = $this->getClassBundle($fqcn);
        $propertyNode = $bundle['property_nodes'][$property] ?? ['tags' => [], 'type' => null];
        return new PropertyMetadata(
            $fqcn,
            $property,
            is_array($propertyNode['tags'] ?? null) ? $propertyNode['tags'] : [],
            is_string($propertyNode['type'] ?? null) ? $propertyNode['type'] : null,
            $bundle['signature'] ?? ''
        );
    }

    public function getLegacyFallbackLogs(): array
    {
        return array_keys(self::$legacyFallbackLogs);
    }

    public function clearLegacyFallbackLogs(): void
    {
        self::$legacyFallbackLogs = [];
    }

    private function readFromAttributesBundle(
        string $tag,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed {
        if (null === $reflector) {
            return null;
        }
        $normalizedTag = strtolower($tag);
        if ($reflector instanceof ReflectionClass) {
            $bundle = $this->getClassBundle($reflector->getName());
            return $bundle['class_tags'][$normalizedTag] ?? null;
        }
        if ($reflector instanceof ReflectionMethod) {
            $bundle = $this->getClassBundle(
                $reflector->getDeclaringClass()->getName(),
            );
            return $bundle['method_tags'][$reflector->getName()][$normalizedTag] ?? null;
        }
        $bundle = $this->getClassBundle(
            $reflector->getDeclaringClass()->getName(),
        );
        $propertyNode = $bundle['property_nodes'][$reflector->getName()] ?? ['tags' => [], 'type' => null];
        $tags = is_array($propertyNode['tags'] ?? null) ? $propertyNode['tags'] : [];
        $type = is_string($propertyNode['type'] ?? null) ? $propertyNode['type'] : null;
        if ($normalizedTag === 'var' && $type !== null) {
            return $tags[$normalizedTag] ?? $type;
        }
        return $tags[$normalizedTag] ?? null;
    }

    private function legacyFallbackDisabledException(
        string $tag,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): MetadataContractException {
        $where = 'unknown';
        if ($reflector instanceof ReflectionMethod) {
            $where = $reflector->getDeclaringClass()->getName() . '::' . $reflector->getName();
        } elseif ($reflector instanceof ReflectionProperty) {
            $where = $reflector->getDeclaringClass()->getName() . '::$' . $reflector->getName();
        } elseif ($reflector instanceof ReflectionClass) {
            $where = $reflector->getName();
        }
        return new MetadataContractException(sprintf(
            '[MetadataContract] Annotation fallback disabled for `%s` at %s',
            $tag,
            $where
        ));
    }

    private function definitionResolver(): MetadataDefinitionResolver
    {
        return new MetadataDefinitionResolver(
            $this->attributesEnabled(),
            $this->annotationsFallbackEnabled(),
            fn (string $tag, ReflectionMethod|ReflectionProperty $reflector): mixed => $this->readFromAttributesBundle(
                $tag,
                $reflector
            ),
            function (string $tag, ReflectionMethod|ReflectionProperty $reflector): void {
                throw $this->legacyFallbackDisabledException($tag, $reflector);
            },
            function (string $fallback): void {
                $this->rememberLegacyFallback($fallback);
            }
        );
    }

    private function tagValueResolver(): MetadataTagValueResolver
    {
        return new MetadataTagValueResolver(
            $this->attributesEnabled(),
            $this->annotationsFallbackEnabled(),
            fn (
                string $tag,
                ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector
            ): mixed => $this->readFromAttributesBundle($tag, $reflector),
            function (
                string $tag,
                ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector
            ): void {
                throw $this->legacyFallbackDisabledException($tag, $reflector);
            },
            function (string $fallback): void {
                $this->rememberLegacyFallback($fallback);
            }
        );
    }

    private function rememberLegacyFallback(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackLogs)) {
            return;
        }
        self::$legacyFallbackLogs[$context] = true;
        Logger::log('[LegacyMetadata] ' . $context, LOG_NOTICE);
    }

    private function attributeBundleBuilder(): MetadataAttributeBundleBuilder
    {
        $builder = $this->attributeBundleBuilder;
        if (!$builder instanceof MetadataAttributeBundleBuilder) {
            $builder = new MetadataAttributeBundleBuilder();
            $this->attributeBundleBuilder = $builder;
        }
        return $builder;
    }

}
