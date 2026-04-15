<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length implements DtoConstraintAttributeContract
{
    public function __construct(public int $min = 0, public ?int $max = null)
    {
    }

    public function validateValue(mixed $value): bool
    {
        if (!is_string($value)) {
            return true;
        }
        $strlen = mb_strlen($value);
        return $strlen >= $this->min && ($this->max === null || $strlen <= $this->max);
    }

    public function errorCode(): string
    {
        return 'invalid_length';
    }
}
