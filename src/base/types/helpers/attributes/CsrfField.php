<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class CsrfField
{
    public function __construct(
        public string $tokenField = '_csrf',
        public string $tokenKeyField = '_csrf_key'
    ) {
    }
}

