<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\AdminFrontendAssetsInstaller;
use RuntimeException;

final class AdminFrontendAssetsInstallerTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-admin-assets-' . bin2hex(random_bytes(8));
        mkdir($this->workspace, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deletePath($this->workspace);
    }

    public function testInstallsTheCompleteBundleAndReplacesOnlyTheMount(): void
    {
        $source = $this->createBundle();
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'admin-v2';
        mkdir($destination . DIRECTORY_SEPARATOR . 'obsolete', 0775, true);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'obsolete' . DIRECTORY_SEPARATOR . 'old.js', 'old');

        (new AdminFrontendAssetsInstaller($source, $destination))->install();

        self::assertSame('<app-root></app-root>', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
        self::assertSame('console.log("v2");', file_get_contents($destination . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'main.js'));
        self::assertDirectoryDoesNotExist($destination . DIRECTORY_SEPARATOR . 'obsolete');
    }

    public function testRejectsAnInvalidBundleWithoutChangingThePublishedAssets(): void
    {
        $source = $this->workspace . DIRECTORY_SEPARATOR . 'invalid';
        mkdir($source, 0775, true);
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'admin-v2';
        mkdir($destination, 0775, true);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'index.html', 'previous');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('index.html');

        try {
            (new AdminFrontendAssetsInstaller($source, $destination))->install();
        } finally {
            self::assertSame('previous', file_get_contents($destination . DIRECTORY_SEPARATOR . 'index.html'));
        }
    }

    public function testReplacingASymlinkDoesNotDeleteItsFormerTarget(): void
    {
        $source = $this->createBundle();
        $formerTarget = $this->workspace . DIRECTORY_SEPARATOR . 'former-target';
        mkdir($formerTarget, 0775, true);
        file_put_contents($formerTarget . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');

        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'admin-v2';
        mkdir(dirname($destination), 0775, true);
        symlink($formerTarget, $destination);

        (new AdminFrontendAssetsInstaller($source, $destination))->install();

        self::assertFalse(is_link($destination));
        self::assertSame('keep', file_get_contents($formerTarget . DIRECTORY_SEPARATOR . 'keep.txt'));
        self::assertFileExists($destination . DIRECTORY_SEPARATOR . 'index.html');
    }

    private function createBundle(): string
    {
        $source = $this->workspace . DIRECTORY_SEPARATOR . 'source';
        mkdir($source . DIRECTORY_SEPARATOR . 'assets', 0775, true);
        file_put_contents($source . DIRECTORY_SEPARATOR . 'index.html', '<app-root></app-root>');
        file_put_contents($source . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'main.js', 'console.log("v2");');
        return $source;
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->deletePath($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }
}
