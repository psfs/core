<?php

namespace PSFS\base\types\helpers\attributes;

use InvalidArgumentException;

trait MetadataStringNormalizerTrait
{
    protected function normalizeString(string $value, string $attributeName, bool $allowEmpty = false): string
    {
        $normalized = trim($value);
        if (!$allowEmpty && $normalized === '') {
            throw new InvalidArgumentException(sprintf('[%s] `value` cannot be empty', $attributeName));
        }
        return $normalized;
    }
}
