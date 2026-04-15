<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Max
{
    public function __construct(public float|int $value)
    {
    }
}

