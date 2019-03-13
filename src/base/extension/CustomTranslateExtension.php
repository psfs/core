<?php
namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\traits\SingletonTrait;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class CustomTranslateExtension
 * @package PSFS\base\extension
 */
class CustomTranslateExtension extends AbstractExtension {
    use SingletonTrait;

    const CUSTOM_LOCALE_SESSION_KEY = '__PSFS_CUSTOM_LOCALE_KEY__';

    protected static $translations = [];
    protected static $locale = 'es_ES';
    protected static $generate = false;
    protected static $filename = '';

    /**
     * @param string $customKey
     * @param bool $forceReload
     * @param bool $useBase
     */
    protected static function checkLoad($customKey = null, $forceReload = false, $useBase = false) {
        if($forceReload) {
            self::dropInstance();
        }
        self::$locale = I18nHelper::extractLocale(Security::getInstance()->getSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY));
        self::$generate = (boolean)Config::getParam('i18n.autogenerate', false);
        if(!$useBase) {
            $customKey = $customKey ?: Security::getInstance()->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
        }
        if(null !== $customKey) {
            Logger::log('[' . self::class . '] Custom key detected: ' . $customKey, LOG_INFO);
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $customKey, self::$locale . '.json']);
        } else {
            self::$filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', self::$locale . '.json']);
        }
        if(file_exists(self::$filename)) {
            Logger::log('[' . self::class . '] Custom locale detected: ' . $customKey . ' [' . self::$locale . ']', LOG_INFO);
            self::$translations = json_decode(file_get_contents(self::$filename), true);
        } elseif(null !== $customKey) {
            self::checkLoad(null, $forceReload, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(new  \Twig_Extensions_TokenParser_Trans());
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('trans', function($message) {
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
    public static function _($message, $customKey = null, $forceReload = false) {
        self::checkLoad($customKey, $forceReload);
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