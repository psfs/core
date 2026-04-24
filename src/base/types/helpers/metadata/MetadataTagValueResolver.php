<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\types\helpers\MetadataDocParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class MetadataTagValueResolver
{
    /**
     * @param callable(string, ReflectionClass|ReflectionMethod|ReflectionProperty|null):mixed $attributeValue
     * @param callable(string, ReflectionClass|ReflectionMethod|ReflectionProperty|null):void $rejectLegacy
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

    public function getTagValue(
        string $tag,
        string $doc,
        mixed $default,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector
    ): mixed {
        if ($this->attributesEnabled) {
            $value = ($this->attributeValue)($tag, $reflector);
            if (null !== $value) {
                return $value;
            }
            $this->handleLegacyTag($tag, $doc, $reflector);
        }
        return $this->readFromDoc($tag, $doc, $default);
    }

    public function hasDeprecated(?ReflectionMethod $method, string $doc): bool
    {
        if ($this->attributesEnabled && null !== $method) {
            $attr = ($this->attributeValue)('deprecated', $method);
            if (null !== $attr) {
                return (bool)$attr;
            }
            $this->handleDeprecatedFallback($method, $doc);
        }
        return $this->annotationsFallbackEnabled && MetadataDocParser::hasDeprecatedTag($doc);
    }

    private function handleLegacyTag(
        string $tag,
        string $doc,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector
    ): void {
        if ($doc === '' || !$this->hasLegacyTag($tag, $doc)) {
            return;
        }
        if (!$this->annotationsFallbackEnabled) {
            ($this->rejectLegacy)($tag, $reflector);
            return;
        }
        ($this->rememberLegacy)('annotation_' . strtolower($tag));
    }

    private function handleDeprecatedFallback(ReflectionMethod $method, string $doc): void
    {
        if ($doc === '' || !MetadataDocParser::hasDeprecatedTag($doc)) {
            return;
        }
        if (!$this->annotationsFallbackEnabled) {
            ($this->rejectLegacy)('deprecated', $method);
            return;
        }
        ($this->rememberLegacy)('annotation_deprecated');
    }

    private function readFromDoc(string $tag, string $doc, mixed $default): mixed
    {
        if ($doc === '') {
            return $default;
        }
        return match ($tag) {
            'http' => MetadataDocParser::readHttpMethod($doc, $default),
            'visible' => MetadataDocParser::readVisibilityFlag($doc),
            'cache' => (bool)MetadataDocParser::readTagValue('cache', $doc, $default ?? false),
            default => MetadataDocParser::readTagValue($tag, $doc, $default),
        };
    }

    private function hasLegacyTag(string $tag, string $doc): bool
    {
        return match (strtolower($tag)) {
            'http' => MetadataDocParser::hasHttpMethodTag($doc),
            default => MetadataDocParser::hasTag(strtolower($tag), $doc),
        };
    }
}
