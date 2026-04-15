<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Nullable
{
    public function __construct(public bool $value = true)
    {
    }

    public function allowsNull(): bool
    {
        return $this->value;
    }
}
