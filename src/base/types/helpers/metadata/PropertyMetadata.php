<?php

namespace PSFS\base\types\helpers\metadata;

final class PropertyMetadata
{
    /**
     * @param array<string, mixed> $tags
     */
    public function __construct(
        public readonly string $className,
        public readonly string $property,
        public readonly array $tags = [],
        public readonly ?string $declaredType = null,
        public readonly string $signature = ''
    ) {
    }
}
