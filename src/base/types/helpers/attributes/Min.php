<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(public float|int $value)
    {
    }
}

