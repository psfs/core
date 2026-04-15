<?php

namespace PSFS\base\dto;

use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Length;
use PSFS\base\types\helpers\attributes\Max;
use PSFS\base\types\helpers\attributes\Min;
use PSFS\base\types\helpers\attributes\Nullable;
use PSFS\base\types\helpers\attributes\Pattern;
use PSFS\base\types\helpers\attributes\Values;
use ReflectionClass;
use ReflectionProperty;

trait ValidatableDtoTrait
{
    private static ?\WeakMap $__validationInputMap = null;
    private ?ValidationResult $__validationResult = null;

    public function validate(?ValidationContext $ctx = null): ValidationResult
    {
        $context = $ctx ?? new ValidationContext($this->getValidationInputData());
        $result = new ValidationResult();
        $reflector = new ReflectionClass($this);
        $publicProperties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);

        $this->applyDefaultValues($publicProperties);
        $this->validateUnknownFields($publicProperties, $context, $result);
        foreach ($publicProperties as $property) {
            $this->validateProperty($property, $context, $result);
        }
        $this->validateCsrfIfRequired($reflector, $context, $result);

        $this->__validationResult = $result;

        return $result;
    }

    public function isValid(?ValidationContext $ctx = null): bool
    {
        return $this->validate($ctx)->isValid();
    }

    /**
     * @return array<int, array{field:string,code:string,message:string}>
     */
    public function getValidationErrors(): array
    {
        return $this->__validationResult?->getErrors() ?? [];
    }

    public function getValidationResult(): ?ValidationResult
    {
        return $this->__validationResult;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function setValidationInputData(array $data): void
    {
        if (self::$__validationInputMap === null) {
            self::$__validationInputMap = new \WeakMap();
        }
        self::$__validationInputMap[$this] = $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidationInputData(): array
    {
        if (self::$__validationInputMap === null || !isset(self::$__validationInputMap[$this])) {
            return [];
        }
        $data = self::$__validationInputMap[$this];
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function applyDefaultValues(array $publicProperties): void
    {
        foreach ($publicProperties as $property) {
            $name = $property->getName();
            $currentValue = $property->getValue($this);
            if ($currentValue !== null) {
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
            $property->setValue($this, $this->checkCastedValue($defaultValue, $varType));
        }
    }

    /**
     * @param array<int, ReflectionProperty> $publicProperties
     */
    private function validateUnknownFields(array $publicProperties, ValidationContext $context, ValidationResult $result): void
    {
        if (!$context->strictUnknownFields) {
            return;
        }
        $allowed = [];
        foreach ($publicProperties as $property) {
            $allowed[$property->getName()] = true;
        }
        $reflector = new ReflectionClass($this);
        $csrfProtected = $this->classAttribute($reflector, CsrfProtected::class);
        if ($csrfProtected instanceof CsrfProtected && $context->enforceCsrf !== false) {
            $csrfField = $this->classAttribute($reflector, CsrfField::class);
            $allowed[$csrfField?->tokenField ?? '_csrf'] = true;
            $allowed[$csrfField?->tokenKeyField ?? '_csrf_key'] = true;
        }

        foreach ($context->payload as $field => $value) {
            if (!is_string($field) || array_key_exists($field, $allowed)) {
                continue;
            }
            $result->addError(
                $field,
                'unknown_field',
                str_replace('%s', "<strong>{$field}</strong>", t('Field %s is not allowed'))
            );
        }
    }

    private function validateProperty(ReflectionProperty $property, ValidationContext $context, ValidationResult $result): void
    {
        $name = $property->getName();
        $doc = (string)($property->getDocComment() ?: '');
        $value = $property->getValue($this);
        $existsInPayload = array_key_exists($name, $context->payload);

        $required = (bool)MetadataReader::getTagValue('required', $doc, false, $property);
        $nullable = $this->propertyAttribute($property, Nullable::class)?->value ?? false;
        if ($required && !$existsInPayload && $value === null) {
            $result->addError($name, 'required', str_replace('%s', "<strong>{$name}</strong>", t('Field %s is required')));
            return;
        }
        if ($value === null) {
            if ($existsInPayload && !$nullable && $required) {
                $result->addError($name, 'null_not_allowed', str_replace('%s', "<strong>{$name}</strong>", t('Field %s is required')));
            }
            return;
        }

        $varType = MetadataReader::extractVarType($property, $doc);
        if (is_string($varType) && !$this->matchesDeclaredType($value, $varType)) {
            $result->addError($name, 'invalid_type', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
            return;
        }

        $valuesAttr = $this->propertyAttribute($property, Values::class);
        $values = $valuesAttr instanceof Values
            ? (is_array($valuesAttr->value) ? $valuesAttr->value : [$valuesAttr->value])
            : InjectorHelper::getValues($doc, $property);
        if (is_array($values) && !in_array($value, $values, true)) {
            $result->addError($name, 'invalid_enum', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
        }

        $pattern = $this->propertyAttribute($property, Pattern::class);
        if ($pattern instanceof Pattern && is_string($value) && preg_match($pattern->value, $value) !== 1) {
            $result->addError($name, 'pattern_mismatch', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
        }

        $length = $this->propertyAttribute($property, Length::class);
        if ($length instanceof Length && is_string($value)) {
            $strlen = mb_strlen($value);
            if ($strlen < $length->min || ($length->max !== null && $strlen > $length->max)) {
                $result->addError($name, 'invalid_length', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
            }
        }

        $min = $this->propertyAttribute($property, Min::class);
        if ($min instanceof Min && is_numeric($value) && (float)$value < $min->value) {
            $result->addError($name, 'min_value', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
        }
        $max = $this->propertyAttribute($property, Max::class);
        if ($max instanceof Max && is_numeric($value) && (float)$value > $max->value) {
            $result->addError($name, 'max_value', str_replace('%s', "<strong>{$name}</strong>", t('Field %s has an invalid format')));
        }
    }

    private function validateCsrfIfRequired(
        ReflectionClass $reflector,
        ValidationContext $context,
        ValidationResult $result
    ): void {
        $csrfProtected = $this->classAttribute($reflector, CsrfProtected::class);
        if (!$csrfProtected instanceof CsrfProtected) {
            return;
        }
        if ($context->enforceCsrf === false) {
            return;
        }

        $csrfField = $this->classAttribute($reflector, CsrfField::class);
        $tokenField = $csrfField?->tokenField ?? '_csrf';
        $tokenKeyField = $csrfField?->tokenKeyField ?? '_csrf_key';
        $formKey = $csrfProtected->formKey !== '' ? $csrfProtected->formKey : $reflector->getShortName();

        $token = '';
        $tokenKey = '';
        if (array_key_exists($tokenField, $context->payload) && is_scalar($context->payload[$tokenField])) {
            $token = (string)$context->payload[$tokenField];
        }
        if (array_key_exists($tokenKeyField, $context->payload) && is_scalar($context->payload[$tokenKeyField])) {
            $tokenKey = (string)$context->payload[$tokenKeyField];
        }
        if ($token === '') {
            $token = (string)($context->header($csrfProtected->headerName) ?? '');
        }
        if ($tokenKey === '' && $csrfProtected->headerKeyName !== '') {
            $tokenKey = (string)($context->header($csrfProtected->headerKeyName) ?? '');
        }

        if (!CsrfValidator::validateSubmission($token, $tokenKey, $formKey)) {
            $result->addError($tokenField, 'invalid_csrf', t('Invalid form'));
        }
    }

    private function matchesDeclaredType(mixed $value, string $type): bool
    {
        $normalized = strtolower(trim($type));
        if (str_contains($normalized, '|')) {
            $types = array_map('trim', explode('|', $normalized));
            foreach ($types as $candidate) {
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
        if (empty($attrs)) {
            return null;
        }
        return $attrs[0]->newInstance();
    }

    private function classAttribute(ReflectionClass $reflector, string $attributeClass): mixed
    {
        $attrs = $reflector->getAttributes($attributeClass);
        if (empty($attrs)) {
            return null;
        }
        return $attrs[0]->newInstance();
    }
}
