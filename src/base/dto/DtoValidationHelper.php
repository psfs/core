<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Nullable;
use ReflectionClass;
use ReflectionProperty;

final class DtoValidationHelper
{
    public static function propertyDoc(ReflectionProperty $property): string
    {
        return (string)($property->getDocComment() ?: '');
    }

    public static function defaultValueFor(ReflectionProperty $property): mixed
    {
        $defaultAttr = self::propertyAttribute($property, DefaultValue::class);
        if ($defaultAttr instanceof DefaultValue) {
            return $defaultAttr->value;
        }

        return MetadataReader::getTagValue('default', self::propertyDoc($property), null, $property);
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     * @return array<string, bool>
     */
    public static function allowedPayloadFields(
        array $publicProperties,
        ReflectionClass $reflector,
        ?bool $enforceCsrf
    ): array {
        $allowed = [];
        foreach ($publicProperties as $property) {
            $allowed[$property->getName()] = true;
        }

        if (!self::requiresCsrfValidation($reflector, $enforceCsrf)) {
            return $allowed;
        }

        [$tokenField, $tokenKeyField] = self::csrfFieldNames(self::csrfField($reflector));
        $allowed[$tokenField] = true;
        $allowed[$tokenKeyField] = true;

        return $allowed;
    }

    public static function requiresCsrfValidation(ReflectionClass $reflector, ?bool $enforceCsrf): bool
    {
        return self::csrfProtection($reflector) instanceof CsrfProtected && $enforceCsrf !== false;
    }

    public static function csrfProtection(ReflectionClass $reflector): ?CsrfProtected
    {
        $attribute = self::classAttribute($reflector, CsrfProtected::class);
        return $attribute instanceof CsrfProtected ? $attribute : null;
    }

    public static function csrfField(ReflectionClass $reflector): ?CsrfField
    {
        $attribute = self::classAttribute($reflector, CsrfField::class);
        return $attribute instanceof CsrfField ? $attribute : null;
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function csrfFieldNames(?CsrfField $csrfField): array
    {
        return [
            $csrfField?->tokenField ?? '_csrf',
            $csrfField?->tokenKeyField ?? '_csrf_key',
        ];
    }

    public static function csrfFormKey(CsrfProtected $csrfProtected, ReflectionClass $reflector): string
    {
        return $csrfProtected->formKey !== '' ? $csrfProtected->formKey : $reflector->getShortName();
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function csrfTokensFromRequest(
        ValidationContext $context,
        CsrfProtected $csrfProtected,
        ?CsrfField $csrfField
    ): array {
        [$tokenField, $tokenKeyField] = self::csrfFieldNames($csrfField);
        $token = self::payloadScalar($context->payload, $tokenField);
        $tokenKey = self::payloadScalar($context->payload, $tokenKeyField);

        if ($token === '') {
            $token = (string)($context->header($csrfProtected->headerName) ?? '');
        }
        if ($tokenKey === '' && $csrfProtected->headerKeyName !== '') {
            $tokenKey = (string)($context->header($csrfProtected->headerKeyName) ?? '');
        }

        return [$token, $tokenKey];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function payloadScalar(array $payload, string $field): string
    {
        if (!array_key_exists($field, $payload) || !is_scalar($payload[$field])) {
            return '';
        }
        return (string)$payload[$field];
    }

    public static function allowsNull(ReflectionProperty $property): bool
    {
        $nullable = self::propertyAttribute($property, Nullable::class);
        return $nullable instanceof Nullable && $nullable->allowsNull();
    }

    public static function hasAttribute(ReflectionProperty $property, string $attributeClass): bool
    {
        return !empty($property->getAttributes($attributeClass));
    }

    public static function matchesDeclaredType(mixed $value, string $type): bool
    {
        $normalized = strtolower(trim($type));
        if (str_contains($normalized, '|')) {
            foreach (array_map('trim', explode('|', $normalized)) as $candidate) {
                if (self::matchesDeclaredType($value, $candidate)) {
                    return true;
                }
            }
            return false;
        }

        return match ($normalized) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'bool', 'boolean' => is_bool($value),
            'float', 'double', 'number' => is_float($value) || is_int($value),
            'array' => is_array($value),
            default => true,
        };
    }

    public static function propertyAttribute(ReflectionProperty $property, string $attributeClass): mixed
    {
        $attrs = $property->getAttributes($attributeClass);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    public static function classAttribute(ReflectionClass $reflector, string $attributeClass): mixed
    {
        $attrs = $reflector->getAttributes($attributeClass);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }
}
