<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\Logger;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{

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
    public static function setLocale() {
        $locale = Config::getParam("default.language", 'es_ES');
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
    public static function utf8Encode($data) {
        if(is_array($data)) {
            foreach($data as $key => &$field) {
                $field = self::utf8Encode($field);
            }
        } elseif(is_object($data)) {
            $properties = get_class_vars($data);
            foreach($properties as $property => $value) {
                $data->$property = self::utf8Encode($data->$property);
            }

        } elseif(is_string($data)) {
            $data = utf8_encode($data);
        }
        return $data;
    }
}