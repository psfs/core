<?php

namespace PSFS\base\types\helpers\metadata;

final class MetadataEntryReader
{
    /**
     * @param callable(string, string):?array $local
     * @param callable(string, string):?array $opcache
     * @param callable(string, string):?array $redis
     */
    public function __construct(
        private $local,
        private $opcache,
        private $redis
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $cacheKey, string $signature): ?array
    {
        foreach ([$this->local, $this->opcache, $this->redis] as $reader) {
            $entry = $reader($cacheKey, $signature);
            if (is_array($entry)) {
                return $entry;
            }
        }

        return null;
    }
}
