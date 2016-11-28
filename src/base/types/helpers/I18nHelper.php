<?php
namespace PSFS\base\types\helpers;

use PSFS\base\Cache;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper {

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
}