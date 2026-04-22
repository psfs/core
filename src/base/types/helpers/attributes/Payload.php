<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Payload implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;
    use MetadataStringNormalizerTrait;

    public function __construct(public string $value)
    {
        $this->value = $this->normalizeString($this->value, 'Payload');
    }

    public static function tag(): string
    {
        return 'payload';
    }
}
