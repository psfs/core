<?php

namespace PSFS\base\types\helpers;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @package PSFS\base\types\helpers
 */
class FileHelper
{
    /**
     * @param mixed $data
     * @param string $path
     * @return int|bool
     */
    public static function writeFile(string $path, mixed $data): int|bool
    {
        return @file_put_contents($path, $data);
    }

    /**
     * @param string $path
     * @param mixed $data
     * @param int $flags
     * @return bool
     */
    public static function writeFileAtomic(string $path, mixed $data, int $flags = 0): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
            return false;
        }
        $existingMode = file_exists($path) ? (fileperms($path) & 0777) : 0644;
        $tmpPath = tempnam($dir, '.tmp-psfs-');
        if (false === $tmpPath) {
            return false;
        }
        $bytes = @file_put_contents($tmpPath, $data, $flags | LOCK_EX);
        if (false === $bytes) {
            @unlink($tmpPath);
            return false;
        }
        if (@rename($tmpPath, $path) === false) {
            @unlink($tmpPath);
            return false;
        }
        @chmod($path, $existingMode > 0 ? $existingMode : 0644);
        return true;
    }

    /**
     * @param string $source
     * @param string $target
     * @return bool
     */
    public static function copyFileAtomic(string $source, string $target): bool
    {
        if (!file_exists($source)) {
            return false;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
            return false;
        }
        $mode = fileperms($source) & 0777;
        $tmpPath = tempnam($dir, '.tmp-psfs-');
        if (false === $tmpPath) {
            return false;
        }
        if (@copy($source, $tmpPath) === false) {
            @unlink($tmpPath);
            return false;
        }
        if (@rename($tmpPath, $target) === false) {
            @unlink($tmpPath);
            return false;
        }
        @chmod($target, $mode > 0 ? $mode : 0644);
        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function deleteFile(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }
        return @unlink($path);
    }

    /**
     * @template T
     * @param string $lockPath
     * @param callable():T $callback
     * @return T|null
     */
    public static function withExclusiveLock(string $lockPath, callable $callback): mixed
    {
        $dir = dirname($lockPath);
        if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
            return null;
        }
        $handle = @fopen($lockPath, 'c+');
        if (false === $handle) {
            return null;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return null;
            }
            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * @param string $path
     * @return string|bool
     */
    public static function readFile(string $path): string|bool
    {
        $data = false;
        if (file_exists($path)) {
            $data = @file_get_contents($path);
        }
        return $data;
    }

    /**
     * @param string $verb
     * @param string $slug
     * @param array $query
     * @return string
     */
    public static function generateHashFilename(string $verb, string $slug, array $query = []): string
    {
        return sha1(strtolower($verb) . ' ' . $slug . ' ' . strtolower(http_build_query($query)));
    }

    /**
     * @param array $action
     * @param array $query
     * @return string
     */
    public static function generateCachePath(array $action, array $query = []): string
    {
        $class = GeneratorHelper::extractClassFromNamespace($action['class']);
        $filename = self::generateHashFilename($action['http'], $action['slug'], $query);
        $subPath = substr($filename, 0, 2) . DIRECTORY_SEPARATOR . substr($filename, 2, 2);
        return $action['module'] . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . $action['method'] . DIRECTORY_SEPARATOR . $subPath . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $path
     * @throws IOException
     */
    public static function deleteDir($path): void
    {
        (new Filesystem())->remove($path);
    }
}
