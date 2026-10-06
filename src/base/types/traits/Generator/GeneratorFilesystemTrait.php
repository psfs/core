<?php

namespace PSFS\base\types\traits\Generator;

use Exception;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\FilesystemTreeHelper;

/**
 * Owns the framework generator's filesystem operations.
 *
 * Requires WEB_DIR and is composed into GeneratorHelper for API compatibility.
 */
trait GeneratorFilesystemTrait
{
    /** @param mixed $dir */
    public static function deleteDir($dir): void
    {
        if (is_string($dir)) {
            FilesystemTreeHelper::deleteDir($dir);
        }
    }

    public static function clearDocumentRoot(): void
    {
        $rootDirs = ['css', 'js', 'media', 'font'];
        foreach ($rootDirs as $dir) {
            $target = WEB_DIR . DIRECTORY_SEPARATOR . $dir;
            $realWebDir = realpath(WEB_DIR);
            $realTarget = realpath($target);
            if (
                file_exists($target)
                && false !== $realWebDir
                && false !== $realTarget
                && str_starts_with($realTarget, $realWebDir . DIRECTORY_SEPARATOR)
            ) {
                try {
                    self::deleteDir($target);
                } catch (\Throwable $e) {
                    syslog(LOG_INFO, $e->getMessage());
                }
            }
        }
    }

    /**
     * @param string $dir
     * @throws GeneratorException
     */
    public static function createDir($dir): void
    {
        if (!empty($dir)) {
            try {
                if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                    throw new Exception(t('Can\'t create directory ') . $dir);
                }
            } catch (\Throwable $e) {
                syslog(LOG_WARNING, $e->getMessage());
                if (!file_exists(dirname($dir))) {
                    throw new GeneratorException($e->getMessage() . $dir);
                }
            }
        }
    }

    /**
     * @param string $dest
     * @param bool $force
     * @param string $filenamePath
     * @param bool $debug
     * @throws GeneratorException
     */
    public static function copyResources($dest, $force, $filenamePath, $debug): void
    {
        if (file_exists($filenamePath)) {
            $destfolder = basename($filenamePath);
            if (!file_exists(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug || $force) {
                if (is_dir($filenamePath)) {
                    self::copyr($filenamePath, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                } else {
                    if (!FileHelper::copyFileAtomic(
                        $filenamePath,
                        WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder
                    )) {
                        throw new ConfigException(
                            "Can't copy " . $filenamePath . " to " . WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string $src
     * @param string $dst
     * @throws GeneratorException
     */
    public static function copyr($src, $dst): void
    {
        FilesystemTreeHelper::copyRecursive((string)$src, (string)$dst);
    }
}
