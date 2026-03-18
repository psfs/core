<?php

namespace PSFS\tests\base\config;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\FileConfigRepository;

class FileConfigRepositoryTest extends TestCase
{
    private string $tmpConfigPath;

    protected function setUp(): void
    {
        $this->tmpConfigPath = CACHE_DIR . DIRECTORY_SEPARATOR . 'tmp_file_config_' . uniqid('', true) . '.json';
        @unlink($this->tmpConfigPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpConfigPath);
    }

    public function testReadReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $repository = new FileConfigRepository($this->tmpConfigPath);
        $this->assertSame([], $repository->read());
    }

    public function testSaveAndReadRoundTrip(): void
    {
        $repository = new FileConfigRepository($this->tmpConfigPath);
        $this->assertTrue($repository->save(['debug' => true, 'cache.var' => 'vtest']));
        $this->assertSame(['debug' => true, 'cache.var' => 'vtest'], $repository->read());
    }

    public function testReadReturnsEmptyArrayForInvalidJson(): void
    {
        file_put_contents($this->tmpConfigPath, '{invalid_json');
        $repository = new FileConfigRepository($this->tmpConfigPath);
        $this->assertSame([], $repository->read());
    }

    public function testInvalidateIsNoOpAndGetSignatureHandlesMissingAndExistingFile(): void
    {
        $repository = new FileConfigRepository($this->tmpConfigPath);
        $this->assertSame('missing:missing', $repository->getFileSignature());
        $repository->invalidate();
        $this->assertTrue($repository->save(['debug' => false]));
        $signature = $repository->getFileSignature();
        $this->assertStringContainsString(':', $signature);
        $this->assertNotSame('missing:missing', $signature);
    }
}
