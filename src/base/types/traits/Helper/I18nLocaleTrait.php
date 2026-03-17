<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\ServerHelper;

trait I18nLocaleTrait
{
    /**
     * Locale allowlist format (xx or xx_YY)
     * @param string $locale
     * @return bool
     */
    public static function isValidLocale(string $locale): bool
    {
        return preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) === 1;
    }

    /**
     * @param string|null $default
     * @return string
     */
    public static function extractLocale(string $default = null): string
    {
        $locale = Request::header('X-API-LANG', $default);
        if (empty($locale)) {
            $locale = Security::getInstance()->getSessionKey(self::PSFS_SESSION_LANGUAGE_KEY);
            if (empty($locale) && ServerHelper::hasServerValue('HTTP_ACCEPT_LANGUAGE')) {
                $browserLocales = explode(",", str_replace("-", "_", ServerHelper::getServerValue("HTTP_ACCEPT_LANGUAGE"))); // brosers use en-US, Linux uses en_US
                for ($i = 0, $ct = count($browserLocales); $i < $ct; $i++) {
                    list($browserLocales[$i]) = explode(";", $browserLocales[$i]); //trick for "en;q=0.8"
                }
                $locale = array_shift($browserLocales);
            }
        }
        $locale = self::normalizeLocale((string)$locale);
        $defaultLocales = explode(',', Config::getParam('i18n.locales', ''));
        if (!in_array($locale, array_merge($defaultLocales, self::$langs))) {
            $locale = Config::getParam('default.language', $default);
        }
        return $locale;
    }

    private static function normalizeLocale(string $locale): string
    {
        $value = strtolower($locale ?: 'es_es');
        if (str_contains($value, '_')) {
            $parts = explode('_', $value);
            $value = $parts[0];
        }
        if ($value === 'en') {
            return 'en_GB';
        }
        return $value . '_' . strtoupper($value);
    }
}
