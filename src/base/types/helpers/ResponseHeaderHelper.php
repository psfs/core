<?php

namespace PSFS\base\types\helpers;

class ResponseHeaderHelper
{
    public const HTTP_STATUS_HEADER = 'Http Status';

    public static function parseHeader(string $header): array
    {
        $line = trim($header);
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
        } else {
            $key = self::HTTP_STATUS_HEADER;
            $value = $line;
        }

        $key = trim($key);
        return [
            'line' => $line,
            'key' => $key,
            'normalized_key' => self::normalizeHeaderKey($key),
            'value' => trim($value),
        ];
    }

    public static function normalizeHeaderKey(string $key): string
    {
        return strtolower(trim($key));
    }

    public static function allowsMultipleValues(string $normalizedKey): bool
    {
        return in_array($normalizedKey, ['set-cookie', 'warning', 'link', 'cache-control'], true);
    }
}
