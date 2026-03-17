<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\i18n\CustomTranslationProvider;
use PSFS\base\types\helpers\i18n\GettextTranslationProvider;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{
    const PSFS_SESSION_LANGUAGE_KEY = '__PSFS_SESSION_LANG_SELECTED__';
    const PSFS_SESSION_LOCALE_KEY = '__PSFS_SESSION_LOCALE_SELECTED__';

    static array $langs = ['es_ES', 'en_GB', 'fr_FR', 'pt_PT', 'de_DE'];
    private static array $missingTranslations = [];

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
        $locale = strtolower($locale ?: 'es_es');
        if (str_contains($locale, '_')) {
            $locale = explode('_', $locale);
            $locale = $locale[0];
        }
        // TODO check more en locales
        if (strtolower($locale) === 'en') {
            $locale = 'en_GB';
        } else {
            $locale = $locale . '_' . strtoupper($locale);
        }
        $defaultLocales = explode(',', Config::getParam('i18n.locales', ''));
        if (!in_array($locale, array_merge($defaultLocales, self::$langs))) {
            $locale = Config::getParam('default.language', $default);
        }
        return $locale;
    }

    /**
     * @param string $absoluteFileName
     * @return array
     * @throws GeneratorException
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
            if(is_array($properties)) {
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
            ['n', 'N', 'c', 'C',],
        ];

        $text = htmlspecialchars($string);
        for ($i = 0, $total = count($from); $i < $total; $i++) {
            $text = str_replace($from[$i], $to[$i], $text);
        }

        return $text;
    }

    /**
     * Método que revisa las traducciones directorio a directorio
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
     * Compile locale PO into MO safely.
     * @param string $localePath
     * @return string
     */
    public static function compileTranslations(string $localePath): string
    {
        $poPath = escapeshellarg($localePath . 'translations.po');
        $moPath = escapeshellarg($localePath . 'translations.mo');
        $command = "export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$poPath} -o {$moPath}";
        return shell_exec($command) ?: '';
    }

    /**
     * @param string $string
     * @return string
     */
    public static function cleanHtmlAttacks(string $string): string {
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $string);
        return preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', "", $value);
    }

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
        if (is_string($customTranslation) && '' !== $customTranslation) {
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
        @file_put_contents($reportPath, json_encode(self::$missingTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Iterates recursively through source folders yielding translation extraction output.
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
                GeneratorHelper::createDir($localePath);
                if (!file_exists($localePath . 'translations.po')) {
                    file_put_contents($localePath . 'translations.po', '');
                }
                $inspectPath = realpath($path . DIRECTORY_SEPARATOR . $fileName);
                if (false !== $inspectPath && is_dir($path . DIRECTORY_SEPARATOR . $fileName) && preg_match('/^\./', $fileName) == 0) {
                    $phpFiles = glob($inspectPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
                    $outputPo = escapeshellarg($localePath . 'translations.po');
                    $commandOutput = '';
                    $cmdPhp = t('No PHP files found in directory');
                    if (!empty($phpFiles)) {
                        $escapedFiles = array_map('escapeshellarg', $phpFiles);
                        $cmdPhp = "export PATH=\$PATH:/opt/local/bin:/bin:/sbin; xgettext " .
                            implode(' ', $escapedFiles) .
                            " --from-code=UTF-8 -j -L PHP --debug --force-po -o {$outputPo}";
                        $commandOutput = shell_exec($cmdPhp) ?: '';
                    }
                    $res = t('Revisando directorio: ') . $inspectPath;
                    $res .= t('Comando ejecutado: ') . $cmdPhp;
                    $res .= $commandOutput;
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
