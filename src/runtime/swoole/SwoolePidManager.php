<?php

namespace PSFS\runtime\swoole;

class SwoolePidManager
{
    public static function readPid(string $pidFile): ?int
    {
        if (!is_file($pidFile)) {
            return null;
        }
        $raw = trim((string)file_get_contents($pidFile));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        $pid = (int)$raw;
        return $pid > 0 ? $pid : null;
    }

    public static function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        $result = exec('kill -0 ' . (int)$pid . ' 2>/dev/null', $output, $code);
        return $result !== false && $code === 0;
    }

    public static function readRunningPid(string $pidFile): ?int
    {
        $pid = self::readPid($pidFile);
        if (null === $pid) {
            return null;
        }
        if (!self::isRunning($pid)) {
            if (is_file($pidFile) && unlink($pidFile) === false) {
                // Keep stale pid file if unable to remove.
            }
            return null;
        }
        return $pid;
    }

    public static function writePid(string $pidFile, int $pid): void
    {
        $dir = dirname($pidFile);
        if (!is_dir($dir) && mkdir($dir, 0775, true) === false && !is_dir($dir)) {
            return;
        }
        file_put_contents($pidFile, (string)$pid);
    }

    public static function removePid(string $pidFile): void
    {
        if (is_file($pidFile) && unlink($pidFile) === false) {
            // Ignore remove failures to keep shutdown flow non-fatal.
        }
    }
}
