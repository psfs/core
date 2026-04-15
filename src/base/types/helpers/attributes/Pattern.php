<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pattern implements DtoConstraintAttributeContract
{
    public function __construct(public string $value)
    {
    }

    public function validateValue(mixed $value): bool
    {
        if (!is_string($value)) {
            return true;
        }
        return preg_match($this->value, $value) === 1;
    }

    public function errorCode(): string
    {
        return 'pattern_mismatch';
    }
}
