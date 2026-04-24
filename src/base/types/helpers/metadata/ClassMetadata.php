<?php

namespace PSFS\base\types\helpers\metadata;

final class ClassMetadata
{
    /**
     * @param array<string, mixed> $tags
     */
    public function __construct(
        public readonly string $className,
        public readonly array $tags = [],
        public readonly string $signature = ''
    ) {
    }
}
