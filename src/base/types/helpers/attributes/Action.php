<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Action
{
    public function __construct(public string $value)
    {
    }
}

