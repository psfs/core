<?php

namespace PSFS\base\types\helpers\attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Values implements MetadataAttributeContract, DtoConstraintAttributeContract
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

    public function validateValue(mixed $value): bool
    {
        if (!is_array($this->value)) {
            return true;
        }
        return in_array($value, $this->value, true);
    }

    public function errorCode(): string
    {
        return 'invalid_enum';
    }
}
