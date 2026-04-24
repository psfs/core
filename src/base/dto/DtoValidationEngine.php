<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DtoConstraintAttributeContract;
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
            $defaultValue = DtoValidationHelper::defaultValueFor($property);
            if ($defaultValue === null || $property->getValue($this->dto) !== null) {
                continue;
            }

            $varType = MetadataReader::extractVarType(
                $property,
                DtoValidationHelper::propertyDoc($property)
            ) ?: 'string';
            $property->setValue($this->dto, ($this->castValue)($defaultValue, $varType));
        }
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function validateUnknownFields(
        array $publicProperties,
        ReflectionClass $reflector,
        ValidationResult $result
    ): void {
        if (!$this->context->strictUnknownFields) {
            return;
        }

        $allowed = DtoValidationHelper::allowedPayloadFields(
            $publicProperties,
            $reflector,
            $this->context->enforceCsrf
        );
        foreach ($this->context->payload as $field => $value) {
            $this->validatePayloadField($field, $allowed, $result);
        }
    }

    /**
     * @param array<string, bool> $allowed
     */
    private function validatePayloadField(mixed $field, array $allowed, ValidationResult $result): void
    {
        if (!is_string($field) || array_key_exists($field, $allowed)) {
            return;
        }

        $result->addError($field, 'unknown_field', $this->messageNotAllowed($field));
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
        $doc = DtoValidationHelper::propertyDoc($property);
        $value = $property->getValue($this->dto);
        $existsInPayload = array_key_exists($name, $this->context->payload);
        $required = (bool)MetadataReader::getTagValue('required', $doc, false, $property);

        if (!$this->validateRequiredProperty($name, $value, $existsInPayload, $required, $result)) {
            return;
        }

        if (!$this->validateNullableProperty($property, $name, $value, $existsInPayload, $required, $result)) {
            return;
        }

        $varType = MetadataReader::extractVarType($property, $doc);
        if (!$this->validatePropertyType($name, $value, $varType, $result)) {
            return;
        }

        $this->validateLegacyEnum($property, $name, $value, $doc, $result);
        $this->validateConstraintAttributes($property, $name, $value, $result);
    }

    private function validateRequiredProperty(
        string $field,
        mixed $value,
        bool $existsInPayload,
        bool $required,
        ValidationResult $result
    ): bool {
        if (!$required || $existsInPayload || $value !== null) {
            return true;
        }

        $result->addError($field, 'required', $this->messageRequired($field));
        return false;
    }

    private function validateNullableProperty(
        ReflectionProperty $property,
        string $field,
        mixed $value,
        bool $existsInPayload,
        bool $required,
        ValidationResult $result
    ): bool {
        if ($value !== null) {
            return true;
        }

        if ($existsInPayload && $required && !DtoValidationHelper::allowsNull($property)) {
            $result->addError($field, 'null_not_allowed', $this->messageRequired($field));
        }

        return false;
    }

    private function validatePropertyType(
        string $field,
        mixed $value,
        ?string $varType,
        ValidationResult $result
    ): bool {
        if (!is_string($varType) || DtoValidationHelper::matchesDeclaredType($value, $varType)) {
            return true;
        }

        $result->addError($field, 'invalid_type', $this->messageInvalidFormat($field));
        return false;
    }

    private function validateLegacyEnum(
        ReflectionProperty $property,
        string $field,
        mixed $value,
        string $doc,
        ValidationResult $result
    ): void {
        if (DtoValidationHelper::hasAttribute($property, Values::class)) {
            return;
        }

        $values = InjectorHelper::getValues($doc, $property);
        if (is_array($values) && !in_array($value, $values, true)) {
            $result->addError($field, 'invalid_enum', $this->messageInvalidFormat($field));
        }
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
        if (!DtoValidationHelper::requiresCsrfValidation($reflector, $this->context->enforceCsrf)) {
            return;
        }

        $csrfProtected = DtoValidationHelper::csrfProtection($reflector);
        assert($csrfProtected instanceof CsrfProtected);
        $csrfField = DtoValidationHelper::csrfField($reflector);
        [$tokenField] = DtoValidationHelper::csrfFieldNames($csrfField);
        $formKey = DtoValidationHelper::csrfFormKey($csrfProtected, $reflector);
        [$token, $tokenKey] = DtoValidationHelper::csrfTokensFromRequest($this->context, $csrfProtected, $csrfField);

        if (!CsrfValidator::validateSubmission($token, $tokenKey, $formKey)) {
            $result->addError($tokenField, 'invalid_csrf', t('Invalid form'));
        }
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
