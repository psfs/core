<?php

namespace PSFS\base\types\traits\Helper;

trait FileAtomicTrait
{
    private static function ensureParentDirectory(string $path): bool
    {
        $dir = dirname($path);
        if (file_exists($dir) && !is_dir($dir)) {
            return false;
        }
        if (!is_dir($dir) && mkdir($dir, 0775, true) === false && !is_dir($dir)) {
            return false;
        }
        return true;
    }

    private static function buildTempPath(string $target): string|false
    {
        return tempnam(dirname($target), '.tmp-psfs-');
    }

    private static function cleanupTempPath(string|false $tmpPath): void
    {
        if (is_string($tmpPath) && file_exists($tmpPath)) {
            @unlink($tmpPath);
        }
    }

    private static function normalizeFileMode(int $mode): int
    {
        return $mode > 0 ? $mode : 0644;
    }

    private static function writeTempAndSwap(string $target, mixed $data, int $flags, int $mode): bool
    {
        $tmpPath = self::buildTempPath($target);
        if (false === $tmpPath) {
            return false;
        }
        $bytes = file_put_contents($tmpPath, $data, $flags | LOCK_EX);
        if (false === $bytes) {
            self::cleanupTempPath($tmpPath);
            return false;
        }
        if (rename($tmpPath, $target) === false) {
            self::cleanupTempPath($tmpPath);
            return false;
        }
        chmod($target, self::normalizeFileMode($mode));
        return true;
    }

    private static function copyTempAndSwap(string $source, string $target, int $mode): bool
    {
        $tmpPath = self::buildTempPath($target);
        if (false === $tmpPath) {
            return false;
        }
        if (copy($source, $tmpPath) === false) {
            self::cleanupTempPath($tmpPath);
            return false;
        }
        if (rename($tmpPath, $target) === false) {
            self::cleanupTempPath($tmpPath);
            return false;
        }
        chmod($target, self::normalizeFileMode($mode));
        return true;
    }

    /**
     * @template T
     * @param string $lockPath
     * @param callable():T $callback
     * @return T|null
     */
    private static function withExclusiveFileLock(string $lockPath, callable $callback): mixed
    {
        $handle = fopen($lockPath, 'c+');
        if (false === $handle) {
            return null;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return null;
            }
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
