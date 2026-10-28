<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\types\helpers\AdminFileHelper;
use PSFS\base\types\helpers\CompatHelper;

class AdminFileHelperTest extends TestCase
{
    private string $dir;
    private int $umask;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-admins-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        $this->umask = umask(0022);
        CompatHelper::reset();
    }

    protected function tearDown(): void
    {
        umask($this->umask);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        CompatHelper::reset();
    }

    private function mode(string $path): int
    {
        clearstatcache(true, $path);
        return fileperms($path) & 0777;
    }

    public function testStoreCreatesTheFileOwnerOnlyEvenWithAPermissiveUmask(): void
    {
        $path = $this->dir . '/admins.json';

        AdminFileHelper::store(['root' => ['hash' => 'x', 'profile' => 'ADMIN']], $path);

        self::assertSame(0600, $this->mode($path));
        self::assertSame(0022, umask(), 'The process umask must be restored');
    }

    public function testStoreKeepsTheContentReadable(): void
    {
        $path = $this->dir . '/admins.json';
        $admins = ['root' => ['hash' => 'abc', 'profile' => 'ADMIN']];

        AdminFileHelper::store($admins, $path);

        self::assertSame($admins, Cache::getInstance()->getDataFromFile($path, Cache::JSONGZ, true));
    }

    public function testStoreRestrictsAFileWrittenByAnOlderVersion(): void
    {
        $path = $this->dir . '/admins.json';
        file_put_contents($path, 'old');
        chmod($path, 0644);

        AdminFileHelper::store(['root' => ['hash' => 'x', 'profile' => 'ADMIN']], $path);

        self::assertSame(0600, $this->mode($path));
    }

    public function testTightenRestrictsGroupAndWorldReadableFiles(): void
    {
        foreach ([0644, 0640, 0604, 0666] as $mode) {
            $path = $this->dir . '/admins-' . decoct($mode) . '.json';
            file_put_contents($path, '{}');
            chmod($path, $mode);

            AdminFileHelper::tighten($path);

            self::assertSame(0600, $this->mode($path), decoct($mode));
        }
    }

    public function testTheReadTimeCheckRunsOnlyOncePerProcess(): void
    {
        $flag = new \ReflectionProperty(AdminFileHelper::class, 'checked');
        AdminFileHelper::resetChecked();
        self::assertFalse($flag->getValue());

        AdminFileHelper::tightenOnce();
        self::assertTrue($flag->getValue());
        AdminFileHelper::tightenOnce();
        self::assertTrue($flag->getValue());

        AdminFileHelper::resetChecked();
        self::assertFalse($flag->getValue());
    }

    public function testTightenLeavesOwnerOnlyFilesAndMissingFilesAlone(): void
    {
        $path = $this->dir . '/admins.json';
        file_put_contents($path, '{}');
        chmod($path, 0400);

        AdminFileHelper::tighten($path);
        AdminFileHelper::tighten($this->dir . '/missing.json');

        self::assertSame(0400, $this->mode($path));
    }
}
