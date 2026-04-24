<?php

namespace PSFS\base\types\helpers\metadata;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

interface MetadataEngineInterface
{
    public function getTagValue(
        string $tag,
        ?string $doc = '',
        mixed $default = null,
        ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector = null
    ): mixed;

    public function hasDeprecated(?ReflectionMethod $method = null, ?string $doc = ''): bool;

    public function extractPayload(string $defaultNamespace, ?ReflectionMethod $method = null, ?string $doc = ''): string;

    public function extractReturnSpec(?ReflectionMethod $method = null, ?string $doc = ''): ?string;

    public function extractVarType(?ReflectionProperty $property, ?string $doc = ''): ?string;

    /**
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     */
    public function resolveInjectableDefinition(?ReflectionProperty $property, ?string $doc = ''): array;

    public function getClassMetadata(string $fqcn): ClassMetadata;

    public function getMethodMetadata(string $fqcn, string $method): MethodMetadata;

    public function getPropertyMetadata(string $fqcn, string $property): PropertyMetadata;

    /**
     * @return array<string, int|float>
     */
    public function getStats(): array;

    public function clearLocalCache(): void;
}
