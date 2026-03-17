<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Cacheable
{
    public function __construct(public bool $value = true)
    {
    }
}

