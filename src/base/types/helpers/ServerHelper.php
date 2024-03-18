<?php
namespace PSFS\base\types\helpers;

/**
 * Server class helper
 */
final class ServerHelper {
    /**
     * @return array
     */
    public static function getServerData() : array {
        return $_SERVER ?? [];
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public static function getServerValue(string $key, $default = null): mixed {
        return self::getServerData()[$key] ?? $default;
    }

    /**
     * @param string $key
     * @return void
     */
    public static function dropServerValue(string $key): void
    {
        if(array_key_exists($key, $_SERVER)) {
            unset($_SERVER[$key]);
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function hasServerValue(string $key): bool
    {
        return array_key_exists($key, $_SERVER);
    }
}
