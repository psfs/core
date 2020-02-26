<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{
    const PSFS_SESSION_LANGUAGE_KEY = '__PSFS_SESSION_LANG_SELECTED__';

    static $langs = ['es_ES', 'en_GB', 'fr_FR', 'pt_PT', 'de_DE'];

    /**
     * @param string $default
     * @return array|mixed|string
     */
    public static function extractLocale($default = null)
    {
        $locale = Request::header('X-API-LANG', $default);
        if (empty($locale)) {
            $locale = Security::getInstance()->getSessionKey(self::PSFS_SESSION_LANGUAGE_KEY);
            if(empty($locale) && array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
                $BrowserLocales = explode(",", str_replace("-", "_", $_SERVER["HTTP_ACCEPT_LANGUAGE"])); // brosers use en-US, Linux uses en_US
                for ($i = 0, $ct = count($BrowserLocales); $i < $ct; $i++) {
                    list($BrowserLocales[$i]) = explode(";", $BrowserLocales[$i]); //trick for "en;q=0.8"
                }
                $locale = array_shift($BrowserLocales);
            }
        }
        $locale = strtolower($locale);
        if (false !== strpos($locale, '_')) {
            $locale = explode('_', $locale);
            $locale = $locale[0];
        }
        // TODO check more en locales
        if (strtolower($locale) === 'en') {
            $locale = 'en_GB';
        } else {
            $locale = $locale . '_' . strtoupper($locale);
        }
        $default_locales = explode(',', Config::getParam('i18n.locales', ''));
        if (!in_array($locale, array_merge($default_locales, self::$langs))) {
            $locale = Config::getParam('default.language', $default);
        }
        return $locale;
    }

    /**
     * Create translation file if not exists
     *
     * @param string $absoluteTranslationFileName
     *
     * @return array
     */
    public static function generateTranslationsFile($absoluteTranslationFileName)
    {
        $translations = array();
        if (file_exists($absoluteTranslationFileName)) {
            @include($absoluteTranslationFileName);
        } else {
            Cache::getInstance()->storeData($absoluteTranslationFileName, "<?php \$translations = array();\n", Cache::TEXT, TRUE);
        }

        return $translations;
    }

    /**
     * Method to set the locale
     * @param string $default
     * @throws \Exception
     */
    public static function setLocale($default = null)
    {
        $locale = self::extractLocale($default);
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
    }

    /**
     * @param $data
     * @return string
     */
    public static function utf8Encode($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => &$field) {
                $field = self::utf8Encode($field);
            }
        } elseif (is_object($data)) {
            $properties = get_class_vars($data);
            foreach ($properties as $property => $value) {
                $data->$property = self::utf8Encode($data->$property);
            }

        } elseif (is_string($data)) {
            $data = utf8_encode($data);
        }
        return $data;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public static function checkI18Class($namespace) {
        $isI18n = false;
        if(preg_match('/I18n$/i', $namespace)) {
            $parentClass = preg_replace('/I18n$/i', '', $namespace);
            if(Router::exists($parentClass)) {
                $isI18n = true;
            }
        }
        return $isI18n;
    }

    /**
     * @param $string
     * @return string
     */
    public static function sanitize($string) {
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
            ['n', 'N', 'c', 'C',],
        ];

        $text = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        for($i = 0, $total = count($from); $i < $total; $i++) {
            $text = str_replace($from[$i],$to[$i], $text);
        }

        return $text;
    }
}
