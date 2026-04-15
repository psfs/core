<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pattern
{
    public function __construct(public string $value)
    {
    }
}

