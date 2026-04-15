<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length
{
    public function __construct(public int $min = 0, public ?int $max = null)
    {
    }
}

