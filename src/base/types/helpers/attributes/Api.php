<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Api
{
    public function __construct(public string $value)
    {
    }
}

