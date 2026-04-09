<?php

namespace PSFS\base\types\helpers;

use PSFS\base\exception\ConfigException;

class FilesystemTreeHelper
{
    public static function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        if (is_link($dir)) {
            @unlink($dir);
            return;
        }
        $objects = scandir($dir) ?: [];
        foreach ($objects as $object) {
            if (self::isDotEntry($object)) {
                continue;
            }
            $path = self::joinPath($dir, $object);
            if (filetype($path) == "dir") {
                self::deleteDir($path);
                continue;
            }
            FileHelper::deleteFile($path);
        }
        reset($objects);
        rmdir($dir);
    }

    public static function copyRecursive(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) {
            throw new ConfigException("Can't open " . $src . " for reading");
        }
        GeneratorHelper::createDir($dst);
        try {
            while (false !== ($file = readdir($dir))) {
                if (self::isDotEntry($file)) {
                    continue;
                }
                $source = self::joinPath($src, $file);
                $target = self::joinPath($dst, $file);
                if (is_dir($source)) {
                    self::copyRecursive($source, $target);
                    continue;
                }
                if (!FileHelper::copyFileAtomic($source, $target)) {
                    throw new ConfigException("Can't copy " . $source . " to " . $target);
                }
            }
        } finally {
            closedir($dir);
        }
    }

    private static function isDotEntry(string $entry): bool
    {
        return $entry === '.' || $entry === '..';
    }

    private static function joinPath(string $base, string $fragment): string
    {
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($fragment, DIRECTORY_SEPARATOR);
    }
}
