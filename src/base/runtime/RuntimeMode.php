<?php

namespace PSFS\base\runtime;

class RuntimeMode
{
    public const ENV_KEY = 'PSFS_RUNTIME';
    public const MODE_SWOOLE = 'swoole';

    public static function getCurrentMode(): string
    {
        $serverValue = $_SERVER[self::ENV_KEY] ?? null;
        if (is_string($serverValue) && '' !== trim($serverValue)) {
            return strtolower(trim($serverValue));
        }
        $envValue = getenv(self::ENV_KEY);
        if (false !== $envValue && '' !== trim((string)$envValue)) {
            return strtolower(trim((string)$envValue));
        }
        return '';
    }

    public static function isLongRunningServer(): bool
    {
        return self::getCurrentMode() === self::MODE_SWOOLE;
    }

    public static function enableSwoole(): void
    {
        putenv(self::ENV_KEY . '=' . self::MODE_SWOOLE);
        $_SERVER[self::ENV_KEY] = self::MODE_SWOOLE;
    }
}
