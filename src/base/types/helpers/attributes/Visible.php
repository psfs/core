<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class Visible implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public bool $value = true)
    {
    }

    public static function tag(): string
    {
        return 'visible';
    }
}
