<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Values implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public string|array $value)
    {
    }

    public static function tag(): string
    {
        return 'values';
    }
}
