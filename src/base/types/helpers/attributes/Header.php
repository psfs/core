<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Header implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;
    use MetadataStringNormalizerTrait;

    public function __construct(public string $value)
    {
        $this->value = $this->normalizeString($this->value, 'Header');
    }

    public static function tag(): string
    {
        return 'header';
    }
}
