<?php

namespace PSFS\base\types\helpers;

class LocaleHelper
{
    /**
     * @return array<int, string>
     */
    public static function buildAvailableLocales(
        string $configuredLocales,
        ?string $sessionLocale,
        string $defaultLocale,
        string $fallbackConfiguredLocales = 'en_US,es_ES'
    ): array {
        $locales = [];
        $configured = trim($configuredLocales) === '' ? $fallbackConfiguredLocales : $configuredLocales;
        foreach (explode(',', $configured) as $locale) {
            $normalized = self::normalizeLocaleCode($locale);
            if (null !== $normalized) {
                $locales[] = $normalized;
            }
        }

        $normalizedSession = self::normalizeLocaleCode((string)$sessionLocale);
        if (null !== $normalizedSession) {
            $locales[] = $normalizedSession;
        }

        $normalizedDefault = self::normalizeLocaleCode($defaultLocale);
        if (null !== $normalizedDefault) {
            $locales[] = $normalizedDefault;
        }

        $locales = array_values(array_unique($locales));
        sort($locales, SORT_NATURAL);
        return $locales;
    }

    public static function normalizeLocaleCode(string $locale): ?string
    {
        $value = trim(str_replace('-', '_', $locale));
        if ('' === $value) {
            return null;
        }
        if (preg_match('/^[a-z]{2}$/i', $value) === 1) {
            return self::normalizeShortLocale($value);
        }
        if (preg_match('/^[a-z]{2}_[a-z]{2}$/i', $value) === 1 || preg_match('/^[a-z]{2}_[A-Z]{2}$/', $value) === 1) {
            return self::normalizeRegionalLocale($value);
        }
        return null;
    }

    private static function normalizeShortLocale(string $locale): string
    {
        $lang = strtolower($locale);
        return $lang === 'en' ? 'en_US' : $lang . '_' . strtoupper($lang);
    }

    private static function normalizeRegionalLocale(string $locale): string
    {
        $parts = explode('_', $locale, 2);
        return strtolower($parts[0]) . '_' . strtoupper($parts[1]);
    }
}
