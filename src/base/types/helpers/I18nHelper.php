<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\Cache;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\traits\Helper\I18nDiscoveryTrait;
use PSFS\base\types\traits\Helper\I18nLocaleTrait;
use PSFS\base\types\traits\Helper\I18nProviderTrait;

/**
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{
    use I18nLocaleTrait;
    use I18nProviderTrait;
    use I18nDiscoveryTrait;

    const PSFS_SESSION_LANGUAGE_KEY = '__PSFS_SESSION_LANG_SELECTED__';
    const PSFS_SESSION_LOCALE_KEY = '__PSFS_SESSION_LOCALE_SELECTED__';

    static array $langs = ['en_US', 'en_GB', 'es_ES', 'fr_FR', 'pt_PT', 'de_DE'];

    /**
     * @param string $absoluteFileName
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateTranslationsFile(string $absoluteFileName): array
    {
        $translations = array();
        if (file_exists($absoluteFileName)) {
            @include($absoluteFileName);
        } else {
            Cache::getInstance()->storeData($absoluteFileName, "<?php \$translations = array();\n", Cache::TEXT, true);
        }

        return $translations;
    }

    /**
     * @param string|null $default
     * @param string|null $customKey
     * @param bool $force
     * @throws Exception
     */
    public static function setLocale(string $default = null, string $customKey = null, bool $force = false): void
    {
        $locale = $force ? $default : self::extractLocale($default);
        $locale = is_string($locale) && $locale !== '' ? $locale : (string)($default ?: 'en_US');
        Inspector::stats('[i18NHelper] Set locale to project [' . $locale . ']', Inspector::SCOPE_DEBUG);
        // Load translations
        putenv("LC_ALL=" . $locale);
        setlocale(LC_ALL, $locale);
        // Keep locale directory available for custom catalogs.
        $localePath = BASE_DIR . DIRECTORY_SEPARATOR . 'locale';
        Logger::log('Set locale dir ' . $localePath);
        GeneratorHelper::createDir($localePath);
        if (self::shouldPersistLocaleSelection($force)) {
            Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, substr($locale, 0, 2));
            Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, $locale);
        }
        if ($force) {
            t('', $customKey, true);
        }
    }

    private static function shouldPersistLocaleSelection(bool $force): bool
    {
        if ($force) {
            return true;
        }
        return null === Request::header('X-API-LANG');
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function utf8Encode($data): mixed
    {
        if (is_array($data)) {
            foreach ($data as &$field) {
                $field = self::utf8Encode($field);
            }
        } elseif (is_object($data)) {
            $properties = get_class_vars(get_class($data));
            foreach (array_keys($properties) as $property) {
                $data->$property = self::utf8Encode($data->$property);
            }
        } elseif (is_string($data)) {
            $data = mb_convert_encoding($data, 'UTF-8');
        }
        return $data;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public static function checkI18Class(string $namespace): bool
    {
        $isI18n = false;
        if (preg_match('/I18n$/i', $namespace)) {
            $parentClass = preg_replace('/I18n$/i', '', $namespace);
            if (Router::exists($parentClass)) {
                $isI18n = true;
            }
        }
        return $isI18n;
    }

    /**
     * @param $string
     * @return string
     */
    public static function sanitize($string): string
    {
        $from = [
            [
                "\u{00E1}",
                "\u{00E0}",
                "\u{00E4}",
                "\u{00E2}",
                "\u{00AA}",
                "\u{00C1}",
                "\u{00C0}",
                "\u{00C2}",
                "\u{00C4}"
            ],
            ["\u{00E9}", "\u{00E8}", "\u{00EB}", "\u{00EA}", "\u{00C9}", "\u{00C8}", "\u{00CA}", "\u{00CB}"],
            ["\u{00ED}", "\u{00EC}", "\u{00EF}", "\u{00EE}", "\u{00CD}", "\u{00CC}", "\u{00CF}", "\u{00CE}"],
            ["\u{00F3}", "\u{00F2}", "\u{00F6}", "\u{00F4}", "\u{00D3}", "\u{00D2}", "\u{00D6}", "\u{00D4}"],
            ["\u{00FA}", "\u{00F9}", "\u{00FC}", "\u{00FB}", "\u{00DA}", "\u{00D9}", "\u{00DB}", "\u{00DC}"],
            ["\u{00F1}", "\u{00D1}", "\u{00E7}", "\u{00C7}"],
        ];
        $to = [
            ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'],
            ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'],
            ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'],
            ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'],
            ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'],
            ['n', 'N', 'c', 'C'],
        ];

        $text = htmlspecialchars($string);
        for ($i = 0, $total = count($from); $i < $total; $i++) {
            $text = str_replace($from[$i], $to[$i], $text);
        }

        return $text;
    }

    /**
     * @param string $string
     * @return string
     */
    public static function cleanHtmlAttacks(string $string): string
    {
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $string);
        return preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', "", $value);
    }
}
