<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;

/**
 * Class DeployHelper
 * @package PSFS\base\types\helpers
 */
final class DeployHelper
{
    const CACHE_VAR_TAG = 'cache.var';

    /**
     * @return string
     * @throws \Exception
     */
    public static function updateCacheVar()
    {
        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone(Config::getParam('project.timezone', 'Europe/Madrid')));
        $config = Config::getInstance()->dumpConfig();
        if (file_exists(CACHE_DIR . DIRECTORY_SEPARATOR . $config[self::CACHE_VAR_TAG] . '.file.cache')) {
            unlink(CACHE_DIR . DIRECTORY_SEPARATOR . $config[self::CACHE_VAR_TAG] . '.file.cache');
        }
        $config[self::CACHE_VAR_TAG] = 'v' . $now->format('Ymd.His');
        Config::save($config);
        return $config[self::CACHE_VAR_TAG];
    }
}
