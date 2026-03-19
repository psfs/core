<?php

namespace PSFS\base\types\helpers;

class SensitiveDataHelper
{
    private const MASK = '***';

    private static array $sensitiveKeys = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'cookie',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
    ];

    public static function redact(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = self::isSensitiveKey((string)$key)
                ? self::MASK
                : self::redact($value);
        }
        return $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));
        foreach (self::$sensitiveKeys as $candidate) {
            if (str_contains($normalized, $candidate)) {
                return true;
            }
        }
        return false;
    }
}
