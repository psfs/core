<?php

namespace PSFS\base\types\helpers\i18n;

class CustomTranslationProvider implements TranslationProviderInterface
{
    public function translate(string $message, string $locale, array $context = []): ?string
    {
        $catalog = $context['catalog'] ?? [];
        if (!is_array($catalog)) {
            return null;
        }
        if (array_key_exists($message, $catalog)) {
            return (string)$catalog[$message];
        }
        $lowerMap = $context['catalog_lowercase_map'] ?? [];
        if (is_array($lowerMap)) {
            $key = mb_convert_case($message, MB_CASE_LOWER, 'UTF-8');
            if (array_key_exists($key, $lowerMap)) {
                $catalogKey = $lowerMap[$key];
                if (array_key_exists($catalogKey, $catalog)) {
                    return (string)$catalog[$catalogKey];
                }
            }
        }
        return null;
    }
}

