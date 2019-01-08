<?php
namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class CustomTranslateExtension
 * @package PSFS\base\extension
 */
class CustomTranslateExtension extends \Twig_Extension {
    use SingletonTrait;

    const CUSTOM_LOCALE_SESSION_KEY = '__PSFS_CUSTOM_LOCALE_KEY__';

    protected static $translations = [];
    protected static $locale = 'es_ES';
    protected static $generate = false;
    protected static $filename = '';

    /**
     * @param string $custom_key
     * @param bool $force_reload
     * @param bool $use_base
     */
    protected static function checkLoad($custom_key = null, $force_reload = false, $use_base = false) {
        if($force_reload) {
            self::dropInstance();
        }
        self::$locale = I18nHelper::extractLocale(Security::getInstance()->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        self::$generate = (boolean)Config::getParam('i18n.autogenerate', false);
        if(!$use_base) {
            $custom_key = $custom_key ?: Security::getInstance()->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
        }
        if(null !== $custom_key) {
            Logger::log('[' . self::class . '] Custom key detected: ' . $custom_key, LOG_INFO);
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $custom_key, self::$locale . '.json']);
        } else {
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', self::$locale . '.json']);
        }
        if(file_exists(self::$filename)) {
            Logger::log('[' . self::class . '] Custom locale detected: ' . $custom_key . ' [' . self::$locale . ']', LOG_INFO);
            self::$translations = json_decode(file_get_contents(self::$filename), true);
        } elseif(null !== $custom_key) {
            self::checkLoad(null, $force_reload, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(new \Twig_Extensions_TokenParser_Trans());
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('trans', function($message) {
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
     * @param string $custom_key
     * @param bool $force_reload
     * @return mixed|string
     */
    public static function _($message, $custom_key = null, $force_reload = false) {
        self::checkLoad($custom_key, $force_reload);
        if(array_key_exists($message, self::$translations)) {
            $translation =  self::$translations[$message];
        } else {
            $translation = gettext($message);
        }
        if(self::$generate) {
            self::generate($message, $translation);
        }
        return $translation;
    }

    /**
     * @param string $message
     * @param string $translation
     */
    protected static function generate($message, $translation) {
        if(!array_key_exists($message, self::$translations)) {
            self::$translations[$message] = $translation;
        }
        file_put_contents(self::$filename, json_encode(array_unique(self::$translations), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}