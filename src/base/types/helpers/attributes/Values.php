<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Values
{
    public function __construct(public string|array $value)
    {
    }
}

