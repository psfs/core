<?php

namespace PSFS\base\types\helpers\metadata;

use PSFS\base\types\helpers\attributes\MetadataAttributeContract;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionType;

final class MetadataAttributeBundleBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(ReflectionClass $reflection, string $signature): array
    {
        return [
            'class_tags' => $this->extractTags($reflection),
            'method_tags' => $this->methodTags($reflection),
            'property_nodes' => $this->propertyNodes($reflection),
            'signature' => $signature,
        ];
    }

    public function propertyType(?ReflectionType $type): ?string
    {
        if (null === $type || (method_exists($type, 'isBuiltin') && $type->isBuiltin())) {
            return null;
        }
        $name = method_exists($type, 'getName') ? $type->getName() : null;
        if (!is_string($name) || $name === '') {
            return null;
        }
        return str_starts_with($name, '\\') ? $name : '\\' . $name;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function methodTags(ReflectionClass $reflection): array
    {
        $methodTags = [];
        foreach ($reflection->getMethods() as $method) {
            $methodTags[$method->getName()] = $this->extractTags($method);
        }
        return $methodTags;
    }

    /**
     * @return array<string, array{tags:array<string, mixed>,type:?string}>
     */
    private function propertyNodes(ReflectionClass $reflection): array
    {
        $propertyNodes = [];
        foreach ($reflection->getProperties() as $property) {
            $propertyNodes[$property->getName()] = [
                'tags' => $this->extractTags($property),
                'type' => $this->propertyType($property->getType()),
            ];
        }
        return $propertyNodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTags(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): array
    {
        $tags = [];
        foreach ($reflector->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof MetadataAttributeContract) {
                $tags[strtolower($instance::tag())] = $instance->resolve();
            }
        }
        return $tags;
    }
}
