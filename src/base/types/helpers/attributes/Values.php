<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Values implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;
    use MetadataStringNormalizerTrait;

    public function __construct(public string|array $value)
    {
        if (is_string($this->value)) {
            $this->value = $this->normalizeString($this->value, 'Values');
            return;
        }
        $this->value = array_map(
            fn ($item) => is_string($item) ? trim($item) : $item,
            $this->value
        );
    }

    public static function tag(): string
    {
        return 'values';
    }
}
