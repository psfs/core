<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\exception\GeneratorException;

trait I18nDiscoveryTrait
{
    /**
     * @param string $path
     * @param string $locale
     * @return array
     * @throws GeneratorException
     */
    public static function findTranslations(string $path, string $locale): array
    {
        if (!self::isValidLocale($locale)) {
            throw new GeneratorException(t('Invalid locale format'));
        }
        $localePath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $localePath .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;
        return iterator_to_array(self::yieldTranslations($path, $localePath), false);
    }

    /**
     * @param string $localePath
     * @return string
     */
    public static function compileTranslations(string $localePath): string
    {
        return t('Legacy PO/MO compilation disabled. Custom JSON catalogs are the only translation source.');
    }

    /**
     * @param string $path
     * @param string $localePath
     * @return \Generator
     * @throws GeneratorException
     */
    private static function yieldTranslations(string $path, string $localePath): \Generator
    {
        if (!file_exists($path)) {
            return;
        }
        $directory = dir($path);
        if (false === $directory) {
            return;
        }
        try {
            while (false !== ($fileName = $directory->read())) {
                $inspectPath = realpath($path . DIRECTORY_SEPARATOR . $fileName);
                if (false !== $inspectPath && is_dir($path . DIRECTORY_SEPARATOR . $fileName) && preg_match(
                        '/^\./',
                        $fileName
                    ) == 0) {
                    $phpFiles = glob($inspectPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
                    $cmdPhp = t('Legacy PO extraction disabled in custom i18n mode');
                    if (!empty($phpFiles)) {
                        $cmdPhp .= ': ' . count($phpFiles) . ' PHP files scanned';
                    }
                    $res = t('Reviewing directory: ') . $inspectPath;
                    $res .= t('Executed command: ') . $cmdPhp;
                    usleep(10);
                    yield $res;
                    yield from self::yieldTranslations($inspectPath, $localePath);
                }
            }
        } finally {
            $directory->close();
        }
    }
}
