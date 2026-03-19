<?php

namespace PSFS\base\types\helpers\attributes;

trait MetadataAttributeValueResolverTrait
{
    public function resolve(): mixed
    {
        return $this->value;
    }
}
