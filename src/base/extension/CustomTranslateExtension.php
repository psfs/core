<?php
namespace PSFS\base\extension;

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

    /**
     * @param string $custom_key
     * @param bool $force_reload
     */
    protected static function checkLoad($custom_key = null, $force_reload = false) {
        if($force_reload) self::dropInstance();
        self::$locale = I18nHelper::extractLocale('es_ES');
        $custom_key = $custom_key ?: Security::getInstance()->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
        if(null !== $custom_key) {
            Logger::log('[' . self::class . '] Custom key detected: ' . $custom_key, LOG_INFO);
            $filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $custom_key, self::$locale . '.json']);
            if(file_exists($filename)) {
                Logger::log('[' . self::class . '] Custom locale detected: ' . $custom_key . ' [' . self::$locale . ']', LOG_INFO);
                self::$translations = json_decode(file_get_contents($filename), true);
            }
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
            return self::$translations[$message];
        } else {
            return gettext($message);
        }
    }
}