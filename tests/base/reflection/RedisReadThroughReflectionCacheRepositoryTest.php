<?php

namespace PSFS\tests\base\reflection;

use PHPUnit\Framework\TestCase;
use PSFS\base\reflection\FileReflectionCacheRepository;
use PSFS\base\reflection\RedisReadThroughReflectionCacheRepository;

class RedisReadThroughReflectionCacheRepositoryTest extends TestCase
{
    public function testReadFallsBackToFileRepositoryWhenRedisUnavailable(): void
    {
        $file = new ReflectionTestFileRepository();
        $repo = new RedisReadThroughReflectionCacheRepository($file, 60, 'vtest');
        $this->setRedis($repo, null);

        $result = $repo->read();

        $this->assertSame(['cache' => '\\PSFS\\base\\Cache'], $result);
        $this->assertSame(1, $file->readCalls);
    }

    public function testReadReturnsRedisPayloadWhenAvailable(): void
    {
        $file = new ReflectionTestFileRepository();
        $repo = new RedisReadThroughReflectionCacheRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn(json_encode(['security' => '\\PSFS\\base\\Security']));
        $redis->expects($this->never())->method('setex');
        $redis->expects($this->never())->method('set');
        $this->setRedis($repo, $redis);

        $result = $repo->read();

        $this->assertSame(['security' => '\\PSFS\\base\\Security'], $result);
        $this->assertSame(0, $file->readCalls);
    }

    public function testSaveWritesBackToRedisAndInvalidatesLatestKey(): void
    {
        $file = new ReflectionTestFileRepository();
        $repo = new RedisReadThroughReflectionCacheRepository($file, 90, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with($this->stringContains('psfs:reflections:latest:'))
            ->willReturn('psfs:reflections:key');
        $redis->expects($this->exactly(2))
            ->method('del');
        $redis->expects($this->once())
            ->method('setex')
            ->with($this->stringContains('psfs:reflections:'), 90, json_encode(['injector' => '\\PSFS\\base\\Cache']));
        $redis->expects($this->once())
            ->method('set')
            ->with($this->stringContains('psfs:reflections:latest:'), $this->stringContains('psfs:reflections:'));
        $this->setRedis($repo, $redis);

        $saved = $repo->save(['injector' => '\\PSFS\\base\\Cache']);

        $this->assertTrue($saved);
        $this->assertSame(1, $file->saveCalls);
    }

    public function testReadAndSaveFallbackOnRedisExceptions(): void
    {
        $file = new ReflectionTestFileRepository();
        $repo = new RedisReadThroughReflectionCacheRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willThrowException(new \RedisException('boom'));
        $redis->method('setex')->willThrowException(new \RedisException('boom'));
        $this->setRedis($repo, $redis);

        $this->assertSame(['cache' => '\\PSFS\\base\\Cache'], $repo->read());
        $this->assertTrue($repo->save(['ok' => true]));
        $this->assertSame(1, $file->readCalls);
        $this->assertSame(1, $file->saveCalls);
    }

    public function testInvalidateAndRefreshWhenRedisDisabled(): void
    {
        $file = new ReflectionTestFileRepository();
        $repo = new RedisReadThroughReflectionCacheRepository($file, 60, 'vtest');
        $this->setRedis($repo, null);

        $repo->invalidate();
        $refreshed = $repo->refresh();

        $this->assertSame(['cache' => '\\PSFS\\base\\Cache'], $refreshed);
        $this->assertSame(1, $file->readCalls);
        $this->assertSame('/tmp/reflections.json', $repo->getCachePath());
        $this->assertSame('mtime:sha1', $repo->getSourceSignature());
    }

    private function setRedis(RedisReadThroughReflectionCacheRepository $repo, ?\Redis $redis): void
    {
        $property = new \ReflectionProperty($repo, 'redis');
        $property->setAccessible(true);
        $property->setValue($repo, $redis);
    }
}

class ReflectionTestFileRepository extends FileReflectionCacheRepository
{
    public int $readCalls = 0;
    public int $saveCalls = 0;

    public function __construct()
    {
    }

    public function read(): array
    {
        $this->readCalls++;
        return ['cache' => '\\PSFS\\base\\Cache'];
    }

    public function save(array $properties): bool
    {
        $this->saveCalls++;
        return true;
    }

    public function getCachePath(): string
    {
        return '/tmp/reflections.json';
    }

    public function getSourceSignature(): string
    {
        return 'mtime:sha1';
    }

    public function getClassName(): string
    {
        return '\\PSFS\\tests\\fixtures\\DummyClass';
    }
}

