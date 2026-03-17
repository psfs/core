<?php

namespace PSFS\base\types\helpers;

use DateTime;
use DateTimeZone;
use Exception;
use PSFS\base\config\Config;

/**
 * Class DeployHelper
 * @package PSFS\base\types\helpers
 */
final class DeployHelper
{
    const CACHE_VAR_TAG = 'cache.var';
    private const ROUTING_META_FILE = 'routes.meta.json';

    /**
     * @return string
     * @throws Exception
     */
    public static function updateCacheVar(): string
    {
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone(Config::getParam('project.timezone', 'Europe/Madrid')));
        $config = Config::getInstance()->dumpConfig();
        $currentVersion = (string)($config[self::CACHE_VAR_TAG] ?? 'v1');
        self::removeTemplateCacheFile($currentVersion);
        $config[self::CACHE_VAR_TAG] = 'v' . $now->format('Ymd.His');
        Config::save($config);
        return $config[self::CACHE_VAR_TAG];
    }

    /**
     * Updates cache.var and cleans generated cache artifacts.
     * @return array{version:string,config_files_cleaned:bool}
     * @throws Exception
     */
    public static function refreshCacheState(): array
    {
        $version = self::updateCacheVar();
        $cleaned = self::clearConfigCacheArtifacts();
        return [
            'version' => $version,
            'config_files_cleaned' => $cleaned,
        ];
    }

    public static function clearConfigCacheArtifacts(): bool
    {
        $cleaned = Config::clearConfigFiles();
        $routingMeta = CONFIG_DIR . DIRECTORY_SEPARATOR . self::ROUTING_META_FILE;
        if (file_exists($routingMeta) && !unlink($routingMeta)) {
            $cleaned = false;
        }
        return $cleaned;
    }

    private static function removeTemplateCacheFile(string $version): void
    {
        $version = trim($version);
        if ('' === $version) {
            return;
        }
        $cacheFile = CACHE_DIR . DIRECTORY_SEPARATOR . $version . '.file.cache';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
