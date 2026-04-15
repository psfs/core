<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class CsrfProtected
{
    public function __construct(
        public string $formKey = '',
        public string $headerName = 'X-CSRF-Token',
        public string $headerKeyName = 'X-CSRF-Key'
    ) {
    }
}

