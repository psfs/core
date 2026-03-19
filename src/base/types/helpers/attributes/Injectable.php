<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Injectable implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public bool $value = true)
    {
    }

    public static function tag(): string
    {
        return 'injectable';
    }
}
