<?php

namespace PSFS\tests\base\reflection;

use PHPUnit\Framework\TestCase;
use PSFS\base\reflection\FileReflectionCacheRepository;
use PSFS\tests\fixtures\DummyClass;

class ReflectionCacheRepositoryTest extends TestCase
{
    private FileReflectionCacheRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FileReflectionCacheRepository(DummyClass::class);
        $this->repository->invalidate();
    }

    protected function tearDown(): void
    {
        $this->repository->invalidate();
    }

    public function testSaveAndReadProperties(): void
    {
        $properties = [
            'cache' => '\\PSFS\\base\\Cache',
            'security' => '\\PSFS\\base\\Security',
        ];

        $this->assertTrue($this->repository->save($properties));
        $this->assertSame($properties, $this->repository->read());
    }

    public function testReadReturnsEmptyWhenSignatureDoesNotMatch(): void
    {
        $properties = ['cache' => '\\PSFS\\base\\Cache'];
        $this->assertTrue($this->repository->save($properties));
        $this->assertFileExists($this->repository->getCachePath());

        file_put_contents($this->repository->getSignaturePath(), 'invalid-signature');
        $this->assertSame([], $this->repository->read());
        $this->assertFileDoesNotExist($this->repository->getCachePath());
    }
}
