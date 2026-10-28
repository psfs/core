<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Cache;

/**
 * config/admins.json holds the credential hashes of the administrators: owner-only permissions, like config.json
 */
final class AdminFileHelper
{
    private static bool $checked = false;

    public static function path(): string
    {
        return CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json';
    }

    /**
     * @throws \PSFS\base\exception\ConfigException
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function store(array $admins, ?string $path = null): void
    {
        $path ??= self::path();
        // A new file must never exist with the permissive process umask, not even for a moment
        $previousUmask = umask(0077);
        try {
            Cache::getInstance()->storeData($path, $admins, Cache::JSONGZ, true);
        } finally {
            umask($previousUmask);
        }
        self::tighten($path);
    }

    /**
     * Restricts an existing file written by an older version (0644) and warns when that is not possible
     */
    public static function tighten(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        clearstatcache(true, $path);
        $mode = fileperms($path) & 0777;
        if (0 === ($mode & 0077)) {
            return;
        }
        if (@chmod($path, 0600)) {
            CompatHelper::warnOnce('admins.perms', sprintf('%s was readable by other users (%04o) and is now 0600', basename($path), $mode));
            return;
        }
        CompatHelper::warnOnce('admins.perms.failed', sprintf('%s is readable by other users (%04o) and could not be restricted: run chmod 600 on it', $path, $mode));
    }

    /**
     * Checks the permissions once per process; called when the administrators are read
     */
    public static function tightenOnce(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;
        self::tighten(self::path());
    }

    public static function resetChecked(): void
    {
        self::$checked = false;
    }
}
