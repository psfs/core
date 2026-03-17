<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(public string $value)
    {
    }
}

