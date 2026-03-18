<?php

namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\traits\SingletonTrait;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\BlockTokenParser;
use Twig\TwigFilter;

/**
 * Class CustomTranslateExtension
 * @package PSFS\base\extension
 */
class CustomTranslateExtension extends AbstractExtension
{
    use SingletonTrait;

    const CUSTOM_LOCALE_SESSION_KEY = '__PSFS_CUSTOM_LOCALE_KEY__';
    const LOCALE_CACHED_VERSION = '__PSFS_LOCALE_VERSION__';
    const LOCALE_CACHED_TAG = '__PSFS_TRANSLATIONS__';

    /**
     * @var array
     */
    protected static $translations = [];

    /**
     * @var array
     */
    protected static $translationsKeys = [];
    /**
     * @var string
     */
    protected static $locale = 'en_US';
    /**
     * @var bool
     */
    protected static $generate = false;
    /**
     * @var string
     */
    protected static $filename = '';

    /**
     * @return array|mixed
     */
    protected static function extractBaseTranslations($locale = null)
    {
        $locale = $locale ?? self::$locale;
        // Gather always the base translations
        $standardTranslations = [];
        self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $locale . '.json']);
        if (file_exists(self::$filename)) {
            $standardTranslations = json_decode(file_get_contents(self::$filename), true);
        }
        return $standardTranslations;
    }

    /**
     * @param string $locale
     * @return void
     */
    protected static function generateTranslationsKeys($locale) {
        self::$translationsKeys[$locale] = [];
        foreach(self::$translations[$locale] as $key => $value) {
            $tKey = mb_convert_case($key, MB_CASE_LOWER, "UTF-8");
            self::$translationsKeys[$locale][$tKey] = $key;
        }
    }

    /**
     * @param string $customKey
     * @param bool $forceReload
     * @param bool $useBase
     */
    protected static function translationsCheckLoad($customKey = null, $forceReload = false, $useBase = false)
    {
        Inspector::stats('[translationsCheckLoad] Start checking translations load', Inspector::SCOPE_DEBUG);
        $session = Security::getInstance();
        $sessionLocale = $session->getSessionKey(I18nHelper::PSFS_SESSION_LOCALE_KEY) ?? $session->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY);
        self::$locale = self::resolveLocale($sessionLocale, $forceReload);
        $locale = self::$locale;
        $version = $session->getSessionKey(self::LOCALE_CACHED_VERSION);
        $configVersion = self::$locale . '_' . Config::getParam('cache.var', 'v1');
        if ($forceReload) {
            self::forceTranslationsReload($locale);
            $version = null;
        }
        $locale = self::hydrateLocaleAliasIfNeeded($locale);
        if (!self::hasTranslationsLoaded($locale)) {
            Inspector::stats('[translationsCheckLoad] Extracting translations', Inspector::SCOPE_DEBUG);
            self::$generate = (boolean)Config::getParam('i18n.autogenerate', false);
            if (null !== $version && $version === $configVersion) {
                Inspector::stats('[translationsCheckLoad] Translations loaded from session', Inspector::SCOPE_DEBUG);
                self::$translations = $session->getSessionKey(self::LOCALE_CACHED_TAG) ?: [];
            } else {
                $effectiveCustomKey = self::resolveCustomKey($customKey, $session, $useBase);
                $loaded = self::loadTranslationsFromCatalog($locale, $effectiveCustomKey, $session, $configVersion);
                if (!$loaded && null !== $effectiveCustomKey) {
                    self::translationsCheckLoad(null, $forceReload, true);
                }
            }
        }
        Inspector::stats('[translationsCheckLoad] Translations loaded', Inspector::SCOPE_DEBUG);
    }

    private static function resolveLocale(?string $sessionLocale, bool $forceReload): string
    {
        if ($forceReload && !empty($sessionLocale)) {
            return (string)$sessionLocale;
        }
        return I18nHelper::extractLocale($sessionLocale);
    }

    private static function forceTranslationsReload(string $locale): void
    {
        Inspector::stats('[translationsCheckLoad] Force translations reload', Inspector::SCOPE_DEBUG);
        self::dropInstance();
        self::$translations[$locale] = [];
    }

    private static function hasTranslationsLoaded(string $locale): bool
    {
        return array_key_exists($locale, self::$translations) && count(self::$translations[$locale]) > 0;
    }

    private static function hydrateLocaleAliasIfNeeded(string $locale): string
    {
        if (self::hasTranslationsLoaded($locale) || strlen($locale) !== 2) {
            return $locale;
        }
        $expandedLocale = $locale . '_' . strtoupper($locale);
        if (array_key_exists($expandedLocale, self::$translations)) {
            self::$translations[self::$locale] = self::$translations[$expandedLocale];
            self::generateTranslationsKeys(self::$locale);
        }
        return $expandedLocale;
    }

    private static function resolveCustomKey(?string $customKey, Security $session, bool $useBase): ?string
    {
        if ($useBase) {
            return $customKey;
        }
        return $customKey ?: $session->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
    }

    private static function loadTranslationsFromCatalog(string $locale, ?string $customKey, Security $session, string $configVersion): bool
    {
        $standardTranslations = self::extractBaseTranslations();
        self::setCustomCatalogFilename($locale, $customKey);
        if (null === $customKey && !empty($standardTranslations)) {
            self::$translations[$locale] = $standardTranslations;
            self::generateTranslationsKeys($locale);
        }
        if (!file_exists(self::$filename)) {
            return false;
        }
        $customTranslations = json_decode((string)file_get_contents(self::$filename), true);
        if (!is_array($customTranslations)) {
            $customTranslations = [];
        }
        Logger::log('[' . self::class . '] Custom locale detected: ' . $customKey . ' [' . $locale . ']', LOG_INFO);
        self::$translations[$locale] = array_merge($standardTranslations, $customTranslations);
        self::generateTranslationsKeys($locale);
        $session->setSessionKey(self::LOCALE_CACHED_TAG, self::$translations);
        $session->setSessionKey(self::LOCALE_CACHED_VERSION, $configVersion);
        return true;
    }

    private static function setCustomCatalogFilename(string $locale, ?string $customKey): void
    {
        if (null !== $customKey) {
            Logger::log('[' . self::class . '] Custom key detected: ' . $customKey, LOG_INFO);
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $customKey, $locale . '.json']);
            return;
        }
        self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $locale . '.json']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return [new BlockTokenParser()];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('trans', function ($message) {
                return self::_($message);
            }),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'PSFSi18n';
    }

    /**
     * @param $message
     * @param string $customKey
     * @param bool $forceReload
     * @return mixed|string
     */
    public static function _($message, $customKey = null, $forceReload = false)
    {
        if(0 === count(self::$translations) || $forceReload) {
            self::translationsCheckLoad($customKey, $forceReload);
        }
        // Set default translation to catch missing strings
        $isDebugMode = (bool)Config::getParam('debug', false);
        $catalog = array_key_exists(self::$locale, self::$translations) ? self::$translations[self::$locale] : [];
        $catalogKeys = array_key_exists(self::$locale, self::$translationsKeys) ? self::$translationsKeys[self::$locale] : [];
        $translation = I18nHelper::translateWithProviders(
            $message,
            self::$locale,
            $catalog,
            $catalogKeys,
            !$forceReload && !$isDebugMode
        );
        if (self::$generate) {
            self::generate($message, $translation);
        }
        return $translation;
    }

    /**
     * @param string $message
     * @param string $translation
     */
    protected static function generate($message, $translation)
    {
        if(!array_key_exists(self::$locale, self::$translations)) {
            self::$translations[self::$locale] = [];
            self::$translationsKeys[self::$locale] = [];
        }
        if (!array_key_exists($message, self::$translations[self::$locale])) {
            self::$translations[self::$locale][$message] = $translation;
            self::$translationsKeys[self::$locale][mb_convert_case($message, MB_CASE_LOWER, "UTF-8")] = $message;
        }
        if (!self::shouldPersistGeneratedTranslation()) {
            return;
        }
        file_put_contents(self::$filename, json_encode(self::$translations[self::$locale], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private static function shouldPersistGeneratedTranslation(): bool
    {
        if (!defined('PSFS_UNIT_TESTING_EXECUTION') || true !== PSFS_UNIT_TESTING_EXECUTION) {
            return true;
        }
        $baseCatalogDir = LOCALE_DIR . DIRECTORY_SEPARATOR . 'custom';
        $filenameDir = dirname(self::$filename);
        $baseRealPath = realpath($baseCatalogDir);
        $filenameDirRealPath = realpath($filenameDir);
        if (false === $baseRealPath || false === $filenameDirRealPath) {
            return true;
        }
        return $filenameDirRealPath !== $baseRealPath;
    }
}
