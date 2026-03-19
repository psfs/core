<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class Label implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public string $value)
    {
    }

    public static function tag(): string
    {
        return 'label';
    }
}
