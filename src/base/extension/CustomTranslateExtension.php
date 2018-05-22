<?php
namespace PSFS\base\extension;

use PSFS\base\Logger;
use PSFS\base\Security;
use PSFS\base\types\helpers\I18nHelper;

/**
 * Class CustomTranslateExtension
 * @package PSFS\base\extension
 */
class CustomTranslateExtension extends \Twig_Extension {
    const CUSTOM_LOCALE_SESSION_KEY = '__PSFS_CUSTOM_LOCALE_KEY__';

    protected $translations = [];
    protected $locale = 'es_ES';

    public function __construct()
    {
        $this->locale = I18nHelper::extractLocale('es_ES');
        $custom_key = Security::getInstance()->getSessionKey(self::CUSTOM_LOCALE_SESSION_KEY);
        if(null !== $custom_key) {
            Logger::log('[' . self::class . '] Custom key detected: ' . $custom_key, LOG_INFO);
            $filename = implode(DIRECTORY_SEPARATOR, [LOCALE_DIR, 'custom', $custom_key, $this->locale . '.json']);
            if(file_exists($filename)) {
                Logger::log('[' . self::class . '] Custom locale detected: ' . $custom_key . ' [' . $this->locale . ']', LOG_INFO);
                $this->translations = json_decode(file_get_contents($filename), true);
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
                if(array_key_exists($message, $this->translations)) {
                    return $this->translations[$message];
                } else {
                    return gettext($message);
                }
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
}