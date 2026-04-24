<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\MetadataDocParser;
use ReflectionProperty;

final class MetadataInjectableResolver
{
    /**
     * @param callable(string, ReflectionProperty):mixed $attributeValue
     * @param callable(string, ReflectionProperty):void $rejectLegacy
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

    /**
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     */
    public function resolve(?ReflectionProperty $property, string $doc): array
    {
        if ($this->attributesEnabled && $property !== null) {
            $injectable = ($this->attributeValue)('injectable', $property);
            if (is_array($injectable) && isset($injectable['class'])) {
                return $this->attributeDefinition($injectable);
            }
            $this->handleLegacy($property, $doc);
        }

        return $this->legacyDefinition($doc) ?? $this->emptyDefinition();
    }

    private function handleLegacy(ReflectionProperty $property, string $doc): void
    {
        if (!$this->hasLegacyInjectable($doc)) {
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
    private function attributeDefinition(array $injectable): array
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
    private function legacyDefinition(string $doc): ?array
    {
        if (!$this->annotationsFallbackEnabled || !$this->hasLegacyInjectable($doc)) {
            return null;
        }
        return $this->legacyClassDefinition($this->legacyClassName($doc));
    }

    /**
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:string}
     */
    private function legacyClassDefinition(string $className): array
    {
        return [
            'isInjectable' => $className !== '',
            'class' => $className !== '' ? $className : null,
            'singleton' => true,
            'required' => true,
            'source' => 'annotation',
        ];
    }

    private function legacyClassName(string $doc): string
    {
        $className = MetadataDocParser::readVarType($doc);
        return is_string($className) ? trim($className) : '';
    }

    private function hasLegacyInjectable(string $doc): bool
    {
        return $doc !== '' && preg_match(InjectorHelper::INJECTABLE_PATTERN, $doc) === 1;
    }

    /**
     * @return array{isInjectable:bool,class:null,singleton:bool,required:bool,source:null}
     */
    private function emptyDefinition(): array
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
