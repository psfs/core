<?php

namespace PSFS\base\types\helpers;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use PSFS\base\types\traits\Helper\FileAtomicTrait;

/**
 * @package PSFS\base\types\helpers
 */
class FileHelper
{
    use FileAtomicTrait;
    /**
     * @param mixed $data
     * @param string $path
     * @return int|bool
     */
    public static function writeFile(string $path, mixed $data): int|bool
    {
        $written = file_put_contents($path, $data);
        return false === $written ? false : $written;
    }

    /**
     * @param string $path
     * @param mixed $data
     * @param int $flags
     * @return bool
     */
    public static function writeFileAtomic(string $path, mixed $data, int $flags = 0): bool
    {
        if (!self::ensureParentDirectory($path)) {
            return false;
        }
        if (is_dir($path)) {
            return false;
        }
        $existingMode = file_exists($path) ? (fileperms($path) & 0777) : 0644;
        return self::writeTempAndSwap($path, $data, $flags, $existingMode);
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
        if (!self::ensureParentDirectory($target)) {
            return false;
        }
        if (is_dir($target)) {
            return false;
        }
        $mode = fileperms($source) & 0777;
        return self::copyTempAndSwap($source, $target, $mode);
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
        if (is_dir($path)) {
            return false;
        }
        return unlink($path);
    }

    /**
     * @template T
     * @param string $lockPath
     * @param callable():T $callback
     * @return T|null
     */
    public static function withExclusiveLock(string $lockPath, callable $callback): mixed
    {
        if (!self::ensureParentDirectory($lockPath)) {
            return null;
        }
        return self::withExclusiveFileLock($lockPath, $callback);
    }

    /**
     * @param string $path
     * @return string|bool
     */
    public static function readFile(string $path): string|bool
    {
        $data = false;
        if (file_exists($path)) {
            $data = file_get_contents($path);
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
