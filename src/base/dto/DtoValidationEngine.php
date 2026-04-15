<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\DtoConstraintAttributeContract;
use PSFS\base\types\helpers\attributes\Nullable;
use PSFS\base\types\helpers\attributes\Values;
use ReflectionClass;
use ReflectionProperty;

final class DtoValidationEngine
{
    /**
     * @param callable(mixed,string):mixed $castValue
     */
    public static function validate(object $dto, ValidationContext $context, callable $castValue): ValidationResult
    {
        $engine = new self($dto, $context, $castValue);
        return $engine->run();
    }

    /**
     * @param callable(mixed,string):mixed $castValue
     */
    private function __construct(
        private object $dto,
        private ValidationContext $context,
        private $castValue
    ) {
    }

    private function run(): ValidationResult
    {
        $result = new ValidationResult();
        $reflector = new ReflectionClass($this->dto);
        $publicProperties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);

        $this->applyDefaultValues($publicProperties);
        $this->validateUnknownFields($publicProperties, $reflector, $result);
        $this->validateProperties($publicProperties, $result);
        $this->validateCsrfIfRequired($reflector, $result);

        return $result;
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function applyDefaultValues(array $publicProperties): void
    {
        foreach ($publicProperties as $property) {
            if ($property->getValue($this->dto) !== null) {
                continue;
            }

            $defaultAttr = $this->propertyAttribute($property, DefaultValue::class);
            $doc = (string)($property->getDocComment() ?: '');
            $defaultValue = $defaultAttr instanceof DefaultValue
                ? $defaultAttr->value
                : MetadataReader::getTagValue('default', $doc, null, $property);
            if ($defaultValue === null) {
                continue;
            }

            $varType = MetadataReader::extractVarType($property, $doc) ?: 'string';
            $property->setValue($this->dto, ($this->castValue)($defaultValue, $varType));
        }
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function validateUnknownFields(array $publicProperties, ReflectionClass $reflector, ValidationResult $result): void
    {
        if (!$this->context->strictUnknownFields) {
            return;
        }

        $allowed = [];
        foreach ($publicProperties as $property) {
            $allowed[$property->getName()] = true;
        }

        $csrfProtected = $this->classAttribute($reflector, CsrfProtected::class);
        if ($csrfProtected instanceof CsrfProtected && $this->context->enforceCsrf !== false) {
            $csrfField = $this->classAttribute($reflector, CsrfField::class);
            $allowed[$csrfField?->tokenField ?? '_csrf'] = true;
            $allowed[$csrfField?->tokenKeyField ?? '_csrf_key'] = true;
        }

        foreach ($this->context->payload as $field => $value) {
            if (!is_string($field) || array_key_exists($field, $allowed)) {
                continue;
            }
            $result->addError($field, 'unknown_field', $this->messageNotAllowed($field));
        }
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function validateProperties(array $publicProperties, ValidationResult $result): void
    {
        foreach ($publicProperties as $property) {
            $this->validateProperty($property, $result);
        }
    }

    private function validateProperty(ReflectionProperty $property, ValidationResult $result): void
    {
        $name = $property->getName();
        $doc = (string)($property->getDocComment() ?: '');
        $value = $property->getValue($this->dto);
        $existsInPayload = array_key_exists($name, $this->context->payload);
        $required = (bool)MetadataReader::getTagValue('required', $doc, false, $property);

        if ($required && !$existsInPayload && $value === null) {
            $result->addError($name, 'required', $this->messageRequired($name));
            return;
        }

        if ($value === null) {
            if ($existsInPayload && !$this->allowsNull($property) && $required) {
                $result->addError($name, 'null_not_allowed', $this->messageRequired($name));
            }
            return;
        }

        $varType = MetadataReader::extractVarType($property, $doc);
        if (is_string($varType) && !$this->matchesDeclaredType($value, $varType)) {
            $result->addError($name, 'invalid_type', $this->messageInvalidFormat($name));
            return;
        }

        if (!$this->hasAttribute($property, Values::class)) {
            $values = InjectorHelper::getValues($doc, $property);
            if (is_array($values) && !in_array($value, $values, true)) {
                $result->addError($name, 'invalid_enum', $this->messageInvalidFormat($name));
            }
        }

        $this->validateConstraintAttributes($property, $name, $value, $result);
    }

    private function validateConstraintAttributes(
        ReflectionProperty $property,
        string $field,
        mixed $value,
        ValidationResult $result
    ): void {
        foreach ($property->getAttributes() as $reflectionAttribute) {
            $attribute = $reflectionAttribute->newInstance();
            if (!$attribute instanceof DtoConstraintAttributeContract) {
                continue;
            }
            if ($attribute->validateValue($value)) {
                continue;
            }
            $result->addError($field, $attribute->errorCode(), $this->messageInvalidFormat($field));
        }
    }

    private function validateCsrfIfRequired(ReflectionClass $reflector, ValidationResult $result): void
    {
        $csrfProtected = $this->classAttribute($reflector, CsrfProtected::class);
        if (!$csrfProtected instanceof CsrfProtected || $this->context->enforceCsrf === false) {
            return;
        }

        $csrfField = $this->classAttribute($reflector, CsrfField::class);
        $tokenField = $csrfField?->tokenField ?? '_csrf';
        $tokenKeyField = $csrfField?->tokenKeyField ?? '_csrf_key';
        $formKey = $csrfProtected->formKey !== '' ? $csrfProtected->formKey : $reflector->getShortName();

        $token = $this->payloadScalar($tokenField);
        $tokenKey = $this->payloadScalar($tokenKeyField);
        if ($token === '') {
            $token = (string)($this->context->header($csrfProtected->headerName) ?? '');
        }
        if ($tokenKey === '' && $csrfProtected->headerKeyName !== '') {
            $tokenKey = (string)($this->context->header($csrfProtected->headerKeyName) ?? '');
        }

        if (!CsrfValidator::validateSubmission($token, $tokenKey, $formKey)) {
            $result->addError($tokenField, 'invalid_csrf', t('Invalid form'));
        }
    }

    private function payloadScalar(string $field): string
    {
        if (!array_key_exists($field, $this->context->payload) || !is_scalar($this->context->payload[$field])) {
            return '';
        }
        return (string)$this->context->payload[$field];
    }

    private function allowsNull(ReflectionProperty $property): bool
    {
        $nullable = $this->propertyAttribute($property, Nullable::class);
        return $nullable instanceof Nullable && $nullable->allowsNull();
    }

    private function hasAttribute(ReflectionProperty $property, string $attributeClass): bool
    {
        return !empty($property->getAttributes($attributeClass));
    }

    private function matchesDeclaredType(mixed $value, string $type): bool
    {
        $normalized = strtolower(trim($type));
        if (str_contains($normalized, '|')) {
            foreach (array_map('trim', explode('|', $normalized)) as $candidate) {
                if ($this->matchesDeclaredType($value, $candidate)) {
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

    private function propertyAttribute(ReflectionProperty $property, string $attributeClass): mixed
    {
        $attrs = $property->getAttributes($attributeClass);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function classAttribute(ReflectionClass $reflector, string $attributeClass): mixed
    {
        $attrs = $reflector->getAttributes($attributeClass);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function messageRequired(string $field): string
    {
        return str_replace('%s', "<strong>{$field}</strong>", t('Field %s is required'));
    }

    private function messageInvalidFormat(string $field): string
    {
        return str_replace('%s', "<strong>{$field}</strong>", t('Field %s has an invalid format'));
    }

    private function messageNotAllowed(string $field): string
    {
        return str_replace('%s', "<strong>{$field}</strong>", t('Field %s is not allowed'));
    }
}

