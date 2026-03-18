<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\types\helpers\i18n\CustomTranslationProvider;
use PSFS\base\types\helpers\i18n\GettextTranslationProvider;

trait I18nProviderTrait
{
    private static array $missingTranslations = [];

    /**
     * @param string $message
     * @param string $locale
     * @param array $catalog
     * @param array $catalogLowerMap
     * @param bool $allowGettext
     * @return string
     */
    public static function translateWithProviders(string $message, string $locale, array $catalog = [], array $catalogLowerMap = [], bool $allowGettext = true): string
    {
        $context = [
            'catalog' => $catalog,
            'catalog_lowercase_map' => $catalogLowerMap,
        ];

        // Keep wrapper compatibility: merged catalog (base + custom override) is always the source of truth.
        $customProvider = new CustomTranslationProvider();
        $customTranslation = $customProvider->translate($message, $locale, $context);
        if (null !== $customTranslation) {
            return $customTranslation;
        }

        if ($allowGettext) {
            $gettextProvider = new GettextTranslationProvider();
            $gettextTranslation = $gettextProvider->translate($message, $locale, $context);
            if (is_string($gettextTranslation) && '' !== $gettextTranslation) {
                if (!array_key_exists($message, $catalog)) {
                    self::reportMissingTranslation($locale, $message);
                }
                return $gettextTranslation;
            }
        }
        self::reportMissingTranslation($locale, $message);
        return $message;
    }

    public static function getMissingTranslationsReport(): array
    {
        return self::$missingTranslations;
    }

    public static function clearMissingTranslationsReport(): void
    {
        self::$missingTranslations = [];
    }

    private static function reportMissingTranslation(string $locale, string $message): void
    {
        if (!array_key_exists($locale, self::$missingTranslations)) {
            self::$missingTranslations[$locale] = [];
        }
        if (!in_array($message, self::$missingTranslations[$locale], true)) {
            self::$missingTranslations[$locale][] = $message;
        }
        $reportPath = Config::getParam('i18n.missing.report.path', CACHE_DIR . DIRECTORY_SEPARATOR . 'i18n_missing.json');
        if (@file_put_contents($reportPath, json_encode(self::$missingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            Logger::log('[I18nProviderTrait::reportMissingTranslation] Unable to save the file: ' . $reportPath, LOG_WARNING);
        }
    }
}
