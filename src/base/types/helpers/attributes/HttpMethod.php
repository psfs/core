<?php

namespace PSFS\base\types\helpers\attributes;

use InvalidArgumentException;

#[\Attribute(\Attribute::TARGET_METHOD)]
class HttpMethod implements MetadataAttributeContract
{
    use MetadataAttributeValueResolverTrait;
    use MetadataStringNormalizerTrait;

    public function __construct(public string $value = 'ALL')
    {
        $method = strtoupper($this->normalizeString($this->value, 'HttpMethod'));
        $allowed = ['ALL', 'GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'OPTIONS'];
        if (!in_array($method, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('[HttpMethod] `%s` is not supported', $method));
        }
        $this->value = $method;
    }

    public static function tag(): string
    {
        return 'http';
    }
}
