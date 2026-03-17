<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpMethod
{
    public function __construct(public string $value = 'ALL')
    {
    }
}

