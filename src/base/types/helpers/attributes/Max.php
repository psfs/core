<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Max implements DtoConstraintAttributeContract
{
    public function __construct(public float|int $value)
    {
    }

    public function validateValue(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return true;
        }
        return (float)$value <= $this->value;
    }

    public function errorCode(): string
    {
        return 'max_value';
    }
}
