<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\GeneratorHelper;

class FileHelperTest extends TestCase
{
    private array $cleanupFiles = [];
    private array $cleanupDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        rsort($this->cleanupDirs);
        foreach ($this->cleanupDirs as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }
    }

    public function testWriteFileAtomicCreatesAndWritesTarget(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $target = $dir . DIRECTORY_SEPARATOR . 'atomic.txt';
        $this->cleanupFiles[] = $target;
        $ok = FileHelper::writeFileAtomic($target, 'hello');
        $this->assertTrue($ok);
        $this->assertSame('hello', (string)file_get_contents($target));
        $mode = fileperms($target) & 0777;
        $this->assertGreaterThan(0, ($mode & 0004), 'File must remain world-readable for web servers');
    }

    public function testLegacyWriteAndReadFileContracts(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-legacy';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $target = $dir . DIRECTORY_SEPARATOR . 'legacy.txt';
        $this->cleanupFiles[] = $target;
        $bytes = FileHelper::writeFile($target, 'legacy-data');
        $this->assertIsInt($bytes);
        $this->assertGreaterThan(0, $bytes);
        $this->assertSame('legacy-data', FileHelper::readFile($target));
        $this->assertFalse(FileHelper::readFile($dir . DIRECTORY_SEPARATOR . 'missing.txt'));
    }

    public function testCopyFileAtomicCopiesSourceToTarget(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-copy';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $source = $dir . DIRECTORY_SEPARATOR . 'source.txt';
        $target = $dir . DIRECTORY_SEPARATOR . 'target.txt';
        file_put_contents($source, 'copy-me');
        $this->cleanupFiles[] = $source;
        $this->cleanupFiles[] = $target;

        $ok = FileHelper::copyFileAtomic($source, $target);
        $this->assertTrue($ok);
        $this->assertSame('copy-me', (string)file_get_contents($target));
        $sourceMode = fileperms($source) & 0777;
        $targetMode = fileperms($target) & 0777;
        $this->assertSame($sourceMode, $targetMode);
    }

    public function testCopyFileAtomicReturnsFalseWhenSourceIsMissing(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-copy-missing';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $target = $dir . DIRECTORY_SEPARATOR . 'target.txt';
        $this->cleanupFiles[] = $target;
        $ok = FileHelper::copyFileAtomic($dir . DIRECTORY_SEPARATOR . 'missing.txt', $target);
        $this->assertFalse($ok);
    }

    public function testDeleteFileHandlesExistingAndMissingFile(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-delete';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $target = $dir . DIRECTORY_SEPARATOR . 'delete.txt';
        file_put_contents($target, 'delete');
        $this->assertTrue(FileHelper::deleteFile($target));
        $this->assertFileDoesNotExist($target);
        $this->assertTrue(FileHelper::deleteFile($target));
    }

    public function testGenerateHashFilenameAndCachePathContracts(): void
    {
        $hashA = FileHelper::generateHashFilename('GET', '/api/item', ['A' => '1']);
        $hashB = FileHelper::generateHashFilename('GET', '/api/item', ['A' => '1']);
        $hashC = FileHelper::generateHashFilename('POST', '/api/item', ['A' => '1']);
        $this->assertSame($hashA, $hashB);
        $this->assertNotSame($hashA, $hashC);

        $action = [
            'class' => 'PSFS\\controller\\ConfigController',
            'http' => 'GET',
            'slug' => '/admin/config',
            'module' => 'PSFS',
            'method' => 'config',
        ];
        $path = FileHelper::generateCachePath($action, ['debug' => '1']);
        $this->assertStringStartsWith('PSFS' . DIRECTORY_SEPARATOR . 'ConfigController' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR, $path);
    }

    public function testWithExclusiveLockExecutesCallbackAndReturnsResult(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-lock';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $lock = $dir . DIRECTORY_SEPARATOR . 'asset.lock';
        $this->cleanupFiles[] = $lock;
        $value = FileHelper::withExclusiveLock($lock, static fn() => 'locked-result');
        $this->assertSame('locked-result', $value);
        $this->assertFileExists($lock);
    }

    public function testDeleteDirRemovesDirectoryTree(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-remove';
        $sub = $dir . DIRECTORY_SEPARATOR . 'nested';
        GeneratorHelper::createDir($sub);
        $file = $sub . DIRECTORY_SEPARATOR . 'a.txt';
        file_put_contents($file, 'x');
        $this->assertDirectoryExists($dir);
        FileHelper::deleteDir($dir);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testWriteFileAtomicReturnsFalseWhenTargetPathIsDirectory(): void
    {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'file-helper-invalid';
        GeneratorHelper::createDir($dir);
        $this->cleanupDirs[] = $dir;

        $targetAsDir = $dir . DIRECTORY_SEPARATOR . 'target';
        GeneratorHelper::createDir($targetAsDir);
        $this->cleanupDirs[] = $targetAsDir;
        $this->assertFalse(FileHelper::writeFileAtomic($targetAsDir, 'nope'));
    }
}
