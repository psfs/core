<?php

namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\traits\SingletonTrait;
use Twig\Extension\AbstractExtension;
use Twig\Extensions\TokenParser\TransTokenParser;
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
     * @var string
     */
    protected static $locale = 'es_ES';
    /**
     * @var bool
     */
    protected static $generate = false;
    /**
     * @var string
     */
    protected static $filename = '';

    /**
     * @param string $customKey
     * @param bool $forceReload
     * @param bool $useBase
     */
    protected static function checkLoad($customKey = null, $forceReload = false, $useBase = false)
    {
        $session = Security::getInstance();
        self::$locale = I18nHelper::extractLocale($session->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        $version = self::$locale . '_' . $session->getSessionKey(self::LOCALE_CACHED_VERSION);
        $configVersion = self::$locale . '_' . $session->getSessionKey('config.cache.var');
        if ($forceReload) {
            self::dropInstance();
            $version = null;
        }
        self::$generate = (boolean)Config::getParam('i18n.autogenerate', false);
        if(null !== $version && $version !== $configVersion) {
            self::$translations = $session->getSessionKey(self::LOCALE_CACHED_TAG);
        } else {
            if (!$useBase) {
                $customKey = $customKey ?: $session->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
            }
            // Gather always the base translations
            $standardTranslations = [];
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', self::$locale . '.json']);
            if(file_exists(self::$filename)) {
                $standardTranslations = json_decode(file_get_contents(self::$filename), true);
            }
            // If the project has custom translations, gather them
            if (null !== $customKey) {
                Logger::log('[' . self::class . '] Custom key detected: ' . $customKey, LOG_INFO);
                self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $customKey, self::$locale . '.json']);
            }
            // Finally we merge base and custom translations to complete all the i18n set
            if (file_exists(self::$filename)) {
                Logger::log('[' . self::class . '] Custom locale detected: ' . $customKey . ' [' . self::$locale . ']', LOG_INFO);
                self::$translations = array_merge($standardTranslations, json_decode(file_get_contents(self::$filename), true));
                $session->setSessionKey(self::LOCALE_CACHED_TAG, self::$translations);
                $session->setSessionKey(self::LOCALE_CACHED_VERSION, $configVersion);
            } elseif (null !== $customKey) {
                self::checkLoad(null, $forceReload, true);
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(new  TransTokenParser());
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
        self::checkLoad($customKey, $forceReload);
        if (is_array(self::$translations) && array_key_exists($message, self::$translations)) {
            $translation = self::$translations[$message];
        } else {
            $translation = gettext($message);
        }
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
        if (!array_key_exists($message, self::$translations)) {
            self::$translations[$message] = $translation;
        }
        file_put_contents(self::$filename, json_encode(array_unique(self::$translations), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
