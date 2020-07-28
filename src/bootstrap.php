<?php
namespace PSFS;

defined('PSFS_START_MEM') or define('PSFS_START_MEM', memory_get_usage());
defined('PSFS_START_TS') or define('PSFS_START_TS', microtime(true));
// PHP under version 7.3 compatibility
defined('JSON_THROW_ON_ERROR') or define('JSON_THROW_ON_ERROR', 4194304);
defined('JSON_INVALID_UTF8_SUBSTITUTE') or define('JSON_INVALID_UTF8_SUBSTITUTE', 2097152);

if(defined('PSFS_PHAR_DIR') && file_exists(PSFS_PHAR_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'environment.php')) {
    @require_once PSFS_PHAR_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'environment.php';
}

defined('SOURCE_DIR') or define('SOURCE_DIR', __DIR__);
if (preg_match('/vendor/', SOURCE_DIR)) {
    defined('BASE_DIR') or define('BASE_DIR', SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    defined('CORE_DIR') or define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'src');
} else {
    defined('BASE_DIR') or define('BASE_DIR', SOURCE_DIR . DIRECTORY_SEPARATOR . '..');
    defined('CORE_DIR') or define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'modules');
}
defined('VENDOR_DIR') or define('VENDOR_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'vendor');
defined('LOG_DIR') or define('LOG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
defined('CACHE_DIR') or define('CACHE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'cache');
defined('CONFIG_DIR') or define('CONFIG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'config');
defined('WEB_DIR') or define('WEB_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'html');
defined('LOCALE_DIR') or define('LOCALE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'locale');

if(!class_exists(bootstrap::class)) {
    /**
     * Class Bootstrap
     * @package PSFS
     */
    class bootstrap {
        protected static $loaded = false;
        public static function load() {
            if(!self::$loaded) {
                defined('PSFS_BOOTSTRAP_LOADED') or define('PSFS_BOOTSTRAP_LOADED', true);
                if(class_exists("\\PSFS\\base\\Logger")) \PSFS\base\Logger::log('Bootstrap initialized', LOG_INFO);
                self::$loaded = true;
            }
        }
    }
    require_once 'functions.php';
}
