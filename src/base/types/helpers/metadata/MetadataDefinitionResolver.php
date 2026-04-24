<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\types\helpers\MetadataDocParser;
use ReflectionMethod;
use ReflectionProperty;

final class MetadataDefinitionResolver
{
    /**
     * @param callable(string, ReflectionMethod|ReflectionProperty):mixed $attributeValue
     * @param callable(string, ReflectionMethod|ReflectionProperty):void $rejectLegacy
     * @param callable(string):void $rememberLegacy
     */
    public function __construct(
        private bool $attributesEnabled,
        private bool $annotationsFallbackEnabled,
        private $attributeValue,
        private $rejectLegacy,
        private $rememberLegacy
    ) {
    }

    public function extractPayload(string $defaultNamespace, ?ReflectionMethod $method, string $doc): string
    {
        $value = $method === null ? null : $this->attributeOrLegacyTag('payload', $method, $doc);
        if ($value === null && $this->annotationsFallbackEnabled) {
            $value = MetadataDocParser::readTagValue('payload', $doc, null);
        }

        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? $defaultNamespace : $value;
    }

    public function extractReturnSpec(?ReflectionMethod $method, string $doc): ?string
    {
        if ($this->attributesEnabled && $method !== null) {
            $value = ($this->attributeValue)('return', $method);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            $this->handleLegacyReturn($method, $doc);
        }

        if (!$this->annotationsFallbackEnabled) {
            return null;
        }
        return $this->legacyReturnSpec($doc);
    }

    public function extractVarType(
        ?ReflectionProperty $property,
        string $doc,
        ?string $propertyType,
        ?array $injectableDefinition
    ): ?string {
        if ($this->attributesEnabled && $property !== null) {
            if (
                ($injectableDefinition['source'] ?? null) === 'attribute'
                && is_string($injectableDefinition['class'])
            ) {
                return $injectableDefinition['class'];
            }
            $value = ($this->attributeValue)('var', $property);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if ($propertyType !== null) {
                return $propertyType;
            }
            $this->handleLegacyTag('var', $property, $doc);
        }

        if (!$this->annotationsFallbackEnabled) {
            return null;
        }
        $type = MetadataDocParser::readVarType($doc);
        return is_string($type) && trim($type) !== '' ? $type : null;
    }

    /**
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     */
    public function resolveInjectableDefinition(?ReflectionProperty $property, string $doc): array
    {
        return $this->injectableResolver()->resolve($property, $doc);
    }

    private function attributeOrLegacyTag(string $tag, ReflectionMethod|ReflectionProperty $reflector, string $doc): mixed
    {
        if (!$this->attributesEnabled) {
            return null;
        }
        $value = ($this->attributeValue)($tag, $reflector);
        if ($value === null) {
            $this->handleLegacyTag($tag, $reflector, $doc);
        }
        return $value;
    }

    private function handleLegacyTag(string $tag, ReflectionMethod|ReflectionProperty $reflector, string $doc): void
    {
        if ($doc === '' || !MetadataDocParser::hasTag($tag, $doc)) {
            return;
        }
        if (!$this->annotationsFallbackEnabled) {
            ($this->rejectLegacy)($tag, $reflector);
            return;
        }
        ($this->rememberLegacy)('annotation_' . $tag);
    }

    private function handleLegacyReturn(ReflectionMethod $method, string $doc): void
    {
        if ($this->legacyReturnSpec($doc) === null) {
            return;
        }
        if (!$this->annotationsFallbackEnabled) {
            ($this->rejectLegacy)('return', $method);
            return;
        }
        ($this->rememberLegacy)('annotation_return');
    }

    private function legacyReturnSpec(string $doc): ?string
    {
        $docReturn = MetadataDocParser::readReturnSpec($doc);
        return is_string($docReturn) && preg_match('/^.*\(.*\)$/', $docReturn) === 1 ? $docReturn : null;
    }

    private function injectableResolver(): MetadataInjectableResolver
    {
        return new MetadataInjectableResolver(
            $this->attributesEnabled,
            $this->annotationsFallbackEnabled,
            $this->attributeValue,
            $this->rejectLegacy,
            $this->rememberLegacy
        );
    }
}
