<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Cacheable implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public bool $value = true)
    {
    }

    public static function tag(): string
    {
        return 'cache';
    }
}
