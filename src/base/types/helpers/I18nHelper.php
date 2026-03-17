<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\Cache;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\traits\Helper\I18nDiscoveryTrait;
use PSFS\base\types\traits\Helper\I18nLocaleTrait;
use PSFS\base\types\traits\Helper\I18nProviderTrait;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{
    use I18nLocaleTrait;
    use I18nProviderTrait;
    use I18nDiscoveryTrait;

    const PSFS_SESSION_LANGUAGE_KEY = '__PSFS_SESSION_LANG_SELECTED__';
    const PSFS_SESSION_LOCALE_KEY = '__PSFS_SESSION_LOCALE_SELECTED__';

    static array $langs = ['es_ES', 'en_GB', 'fr_FR', 'pt_PT', 'de_DE'];

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
            Cache::getInstance()->storeData($absoluteFileName, "<?php \$translations = array();\n", Cache::TEXT, TRUE);
        }

        return $translations;
    }

    /**
     * Method to set the locale
     * @param string|null $default
     * @param string|null $customKey
     * @param bool $force
     * @throws Exception
     */
    public static function setLocale(string $default = null, string $customKey = null, bool $force = false): void
    {
        $locale = $force ? $default : self::extractLocale($default);
        Inspector::stats('[i18NHelper] Set locale to project [' . $locale . ']', Inspector::SCOPE_DEBUG);
        // Load translations
        putenv("LC_ALL=" . $locale);
        setlocale(LC_ALL, $locale);
        // Load the locale path
        $localePath = BASE_DIR . DIRECTORY_SEPARATOR . 'locale';
        Logger::log('Set locale dir ' . $localePath);
        GeneratorHelper::createDir($localePath);
        bindtextdomain('translations', $localePath);
        textdomain('translations');
        bind_textdomain_codeset('translations', 'UTF-8');
        Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, substr($locale, 0, 2));
        Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY, $locale);
        if ($force) {
            t('', $customKey, true);
        }
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
            $properties = get_class_vars($data);
            if (is_array($properties)) {
                foreach (array_keys($properties) as $property) {
                    $data->$property = self::utf8Encode($data->$property);
                }
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
            ['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'],
            ['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'],
            ['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'],
            ['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'],
            ['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'],
            ['ñ', 'Ñ', 'ç', 'Ç'],
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
