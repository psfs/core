<?php

namespace PSFS\tests\base\reflection;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
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

    public function testRefreshInvalidatesExistingCacheAndReturnsEmpty(): void
    {
        $properties = ['security' => '\\PSFS\\base\\Security'];
        $this->assertTrue($this->repository->save($properties));
        $this->assertFileExists($this->repository->getCachePath());

        $refreshed = $this->repository->refresh();

        $this->assertSame([], $refreshed);
        $this->assertFileDoesNotExist($this->repository->getCachePath());
        $this->assertFileDoesNotExist($this->repository->getSignaturePath());
    }

    public function testGetSourceSignatureFallsBackToClassHashWhenClassDoesNotExist(): void
    {
        $missingClass = '\\PSFS\\tests\\fixtures\\MissingClass' . uniqid('', true);
        $repository = new FileReflectionCacheRepository($missingClass);

        $signature = $repository->getSourceSignature();

        $this->assertStringStartsWith('class:', $signature);
        $this->assertSame(ltrim($missingClass, '\\'), $repository->getClassName());
    }

    public function testReadReturnsEmptyArrayWhenCachePayloadIsNotArray(): void
    {
        $cache = $this->createMock(Cache::class);
        $cache->expects($this->once())
            ->method('getDataFromFile')
            ->with($this->anything(), Cache::JSON)
            ->willReturn('not-an-array');

        $repository = new FileReflectionCacheRepository(DummyClass::class, $cache);
        $this->assertSame([], $repository->read());
    }

    public function testSaveReturnsFalseWhenCacheStoreThrowsThrowable(): void
    {
        $cache = $this->createMock(Cache::class);
        $cache->method('storeData')->willThrowException(new \RuntimeException('cache-store-error'));
        $repository = new FileReflectionCacheRepository(DummyClass::class, $cache);

        $this->assertFalse($repository->save(['cache' => '\\PSFS\\base\\Cache']));
    }
}
