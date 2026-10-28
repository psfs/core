<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Logger;

/**
 * Compatibility switches for security hardening.
 *
 * Every hardening ships with the safe behaviour as the default and a switch that restores the previous one.
 * A restored legacy behaviour is reported once per process through the security log channel, so it shows up
 * with the default log level and can be removed before the switch disappears in a future major version.
 * A switch can be set in the PSFS configuration or through its PSFS_<KEY> environment variable.
 */
final class CompatHelper
{
    /** @var array<string,true> */
    private static array $warned = [];

    public static function flag(string $key, bool $default = false): bool
    {
        $value = LogHelper::setting($key);
        if (null === $value) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        $value = LogHelper::setting($key);
        return null !== $value && is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Logs a compatibility warning the first time an id is seen in this process
     */
    public static function warnOnce(string $id, string $message, array $context = []): void
    {
        if (isset(self::$warned[$id])) {
            return;
        }
        self::$warned[$id] = true;
        Logger::log('[Compat] ' . $message, LOG_WARNING, $context, false, LogHelper::CHANNEL_SECURITY);
    }

    public static function reset(): void
    {
        self::$warned = [];
    }
}
