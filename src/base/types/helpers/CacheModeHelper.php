<?php

namespace PSFS\base\types\helpers;

final class CacheModeHelper
{
    public const MODE_NONE = 'NONE';
    public const MODE_MEMORY = 'MEMORY';
    public const MODE_OPCACHE = 'OPCACHE';
    public const MODE_REDIS = 'REDIS';

    public static function normalize(mixed $value): string
    {
        $candidate = strtoupper(trim((string)$value));
        return in_array($candidate, self::all(), true) ? $candidate : self::MODE_NONE;
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::MODE_NONE,
            self::MODE_MEMORY,
            self::MODE_OPCACHE,
            self::MODE_REDIS,
        ];
    }
}
