<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Api implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public string $value)
    {
    }

    public static function tag(): string
    {
        return 'api';
    }
}
