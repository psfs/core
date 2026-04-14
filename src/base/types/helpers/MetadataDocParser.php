<?php

namespace PSFS\base\types\helpers;

class MetadataDocParser
{
    public static function readTagValue(string $tag, string $doc, mixed $default = null): mixed
    {
        preg_match('/@' . preg_quote($tag, '/') . '\ (.*)(\n|\r)/im', $doc, $matches);
        return !empty($matches) ? $matches[1] : $default;
    }

    public static function readHttpMethod(string $doc, mixed $default = null): string
    {
        preg_match('/@(GET|POST|PUT|DELETE|HEAD|PATCH)(\n|\r)/i', $doc, $routeMethod);
        return !empty($routeMethod) ? strtoupper($routeMethod[1]) : ($default ?? 'ALL');
    }

    public static function readVisibilityFlag(string $doc): bool
    {
        $value = (string)self::readTagValue('visible', $doc, '');
        return !str_contains($value, 'false');
    }

    public static function readVarType(string $doc): ?string
    {
        $type = null;
        if (preg_match(InjectorHelper::VAR_PATTERN, $doc, $matches) === 1) {
            list(, $type) = $matches;
        }
        return is_string($type) ? $type : null;
    }
}

