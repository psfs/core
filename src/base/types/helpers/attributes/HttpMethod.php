<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpMethod implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;

    public function __construct(public string $value = 'ALL')
    {
    }

    public static function tag(): string
    {
        return 'http';
    }
}
