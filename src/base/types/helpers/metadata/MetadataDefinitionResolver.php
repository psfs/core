<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\types\helpers\InjectorHelper;
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
        if ($this->attributesEnabled && $property !== null) {
            $injectable = ($this->attributeValue)('injectable', $property);
            if (is_array($injectable) && isset($injectable['class'])) {
                return $this->attributeInjectableDefinition($injectable);
            }
            $this->handleLegacyInjectable($property, $doc);
        }

        return $this->legacyInjectableDefinition($doc) ?? $this->emptyInjectableDefinition();
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

    private function handleLegacyInjectable(ReflectionProperty $property, string $doc): void
    {
        if ($doc === '' || preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) !== 1) {
            return;
        }
        if (!$this->annotationsFallbackEnabled) {
            ($this->rejectLegacy)('injectable', $property);
            return;
        }
        ($this->rememberLegacy)('annotation_injectable');
    }

    /**
     * @param array<string, mixed> $injectable
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:string}
     */
    private function attributeInjectableDefinition(array $injectable): array
    {
        return [
            'isInjectable' => true,
            'class' => is_string($injectable['class']) ? $injectable['class'] : null,
            'singleton' => (bool)($injectable['singleton'] ?? true),
            'required' => (bool)($injectable['required'] ?? true),
            'source' => 'attribute',
        ];
    }

    /**
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:string}|null
     */
    private function legacyInjectableDefinition(string $doc): ?array
    {
        if (
            !$this->annotationsFallbackEnabled
            || $doc === ''
            || preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) !== 1
        ) {
            return null;
        }
        $className = MetadataDocParser::readVarType($doc);
        $className = is_string($className) ? trim($className) : '';

        return [
            'isInjectable' => $className !== '',
            'class' => $className !== '' ? $className : null,
            'singleton' => true,
            'required' => true,
            'source' => 'annotation',
        ];
    }

    /**
     * @return array{isInjectable:bool,class:null,singleton:bool,required:bool,source:null}
     */
    private function emptyInjectableDefinition(): array
    {
        return [
            'isInjectable' => false,
            'class' => null,
            'singleton' => true,
            'required' => true,
            'source' => null,
        ];
    }
}
