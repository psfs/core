<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{

    static $langs = ['es_ES', 'en_GB', 'fr_FR'];

    /**
     * @param string $default
     * @return array|mixed|string
     */
    private static function extractLocale($default = null)
    {
        $locale = Request::header('X-API-LANG', self::$langs[0]);
        if (empty($locale)) {
            $BrowserLocales = explode(",", str_replace("-", "_", $_SERVER["HTTP_ACCEPT_LANGUAGE"])); // brosers use en-US, Linux uses en_US
            for ($i = 0; $i < count($BrowserLocales); $i++) {
                list($BrowserLocales[$i]) = explode(";", $BrowserLocales[$i]); //trick for "en;q=0.8"
            }
            $locale = array_shift($BrowserLocales);
        } else {
            $locale = strtolower($locale);
        }
        if (false !== strpos($locale, '_')) {
            $locale = explode('_', $locale);
            if ($locale[0] == 'en') {
                $locale = $locale[0] . '_GB';
            } else {
                $locale = $locale[0] . '_' . strtoupper($locale[1]);
            }
        } else {
            if (strtolower($locale) === 'en') {
                $locale = 'en_GB';
            } else {
                $locale = $locale . '_' . strtoupper($locale);
            }
        }
        if (!in_array($locale, self::$langs)) {
            $locale = Config::getParam("default.language", $default);
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
     */
    public static function setLocale()
    {
        $locale = self::extractLocale('es_ES');
        Logger::log('Set locale to project [' . $locale . ']');
        // Load translations
        putenv("LC_ALL=" . $locale);
        setlocale(LC_ALL, $locale);
        // Load the locale path
        $locale_path = BASE_DIR . DIRECTORY_SEPARATOR . 'locale';
        Logger::log('Set locale dir ' . $locale_path);
        GeneratorHelper::createDir($locale_path);
        bindtextdomain('translations', $locale_path);
        textdomain('translations');
        bind_textdomain_codeset('translations', 'UTF-8');
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
}