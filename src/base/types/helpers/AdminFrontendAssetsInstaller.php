<?php

namespace PSFS\base\types\helpers;

use RuntimeException;

/**
 * Publishes the versioned Admin frontend bundle into a project's document root.
 */
final class AdminFrontendAssetsInstaller
{
    public function __construct(
        private readonly string $source,
        private readonly string $destination,
    ) {
    }

    public function install(): void
    {
        $this->assertSourceIsBundle();

        $parent = dirname($this->destination);
        GeneratorHelper::createDir($parent);
        $temporary = $this->temporaryPath('install');
        $backup = $this->temporaryPath('backup');
        $hasBackup = false;

        try {
            FilesystemTreeHelper::copyRecursive($this->source, $temporary);
            $this->assertBundleIndex($temporary);

            if ($this->pathExists($this->destination)) {
                if (!@rename($this->destination, $backup)) {
                    throw new RuntimeException('Unable to prepare the current Admin frontend assets for replacement.');
                }
                $hasBackup = true;
            }

            if (!@rename($temporary, $this->destination)) {
                throw new RuntimeException('Unable to publish the Admin frontend assets.');
            }

            if ($hasBackup) {
                $this->deletePath($backup);
            }
        } catch (\Throwable $exception) {
            $this->deletePath($temporary);
            if ($hasBackup && !$this->pathExists($this->destination)) {
                @rename($backup, $this->destination);
            }
            throw $exception;
        }
    }

    private function assertSourceIsBundle(): void
    {
        if (!is_dir($this->source)) {
            throw new RuntimeException('Admin frontend bundle directory not found: ' . $this->source);
        }
        $this->assertBundleIndex($this->source);
    }

    private function assertBundleIndex(string $path): void
    {
        if (!is_file($path . DIRECTORY_SEPARATOR . 'index.html')) {
            throw new RuntimeException('Admin frontend bundle must contain index.html: ' . $path);
        }
    }

    private function temporaryPath(string $purpose): string
    {
        return dirname($this->destination)
            . DIRECTORY_SEPARATOR
            . '.' . basename($this->destination) . '.' . $purpose . '-' . bin2hex(random_bytes(8));
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (is_dir($path)) {
            FilesystemTreeHelper::deleteDir($path);
        }
    }
}
