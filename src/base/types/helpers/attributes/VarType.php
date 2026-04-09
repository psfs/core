<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class VarType implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;
    use MetadataStringNormalizerTrait;

    public function __construct(public string $value)
    {
        $this->value = $this->normalizeString($this->value, 'VarType');
    }

    public static function tag(): string
    {
        return 'var';
    }
}
