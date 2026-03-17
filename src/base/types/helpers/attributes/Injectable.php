<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Injectable
{
    public function __construct(public bool $value = true)
    {
    }
}

