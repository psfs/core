<?php

namespace PSFS\tests\base\config;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\FileConfigRepository;
use PSFS\base\config\RedisReadThroughConfigRepository;

class RedisReadThroughConfigRepositoryTest extends TestCase
{
    private string $tmpConfigPath;

    protected function setUp(): void
    {
        $this->tmpConfigPath = CACHE_DIR . DIRECTORY_SEPARATOR . 'tmp_config_' . uniqid('', true) . '.json';
        @unlink($this->tmpConfigPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpConfigPath);
    }

    public function testReadFallsBackToFileWhenRedisUnavailable(): void
    {
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['app' => 'psfs']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $this->setRedis($repo, null);

        $result = $repo->read();

        $this->assertSame(['app' => 'psfs'], $result);
        $this->assertSame(1, $file->readCalls);
    }

    public function testReadReturnsDecodedCachedPayloadWhenRedisHit(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['from' => 'file']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn(json_encode(['from' => 'redis']));
        $redis->expects($this->never())->method('setex');
        $redis->expects($this->never())->method('set');
        $this->setRedis($repo, $redis);

        $result = $repo->read();

        $this->assertSame(['from' => 'redis'], $result);
        $this->assertSame(0, $file->readCalls);
    }

    public function testReadStoresDataInRedisWhenCacheMiss(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['from' => 'file']);
        $repo = new RedisReadThroughConfigRepository($file, 120, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn('');
        $redis->expects($this->once())
            ->method('setex')
            ->with($this->stringContains('psfs:config:'), 120, json_encode(['from' => 'file']));
        $redis->expects($this->once())
            ->method('set')
            ->with($this->stringContains('psfs:config:latest:'), $this->stringContains('psfs:config:'));
        $this->setRedis($repo, $redis);

        $result = $repo->read();

        $this->assertSame(['from' => 'file'], $result);
        $this->assertSame(1, $file->readCalls);
    }

    public function testReadFallsBackToFileOnRedisException(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['fallback' => true]);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willThrowException(new \RedisException('boom'));
        $this->setRedis($repo, $redis);

        $this->assertSame(['fallback' => true], $repo->read());
        $this->assertSame(1, $file->readCalls);
    }

    public function testReadRehydratesFromFileWhenRedisPayloadIsMalformed(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['from' => 'file']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn('{invalid-json');
        $redis->expects($this->once())
            ->method('setex')
            ->with($this->stringContains('psfs:config:'), 60, json_encode(['from' => 'file']));
        $redis->expects($this->once())
            ->method('set')
            ->with($this->stringContains('psfs:config:latest:'), $this->stringContains('psfs:config:'));
        $this->setRedis($repo, $redis);

        $this->assertSame(['from' => 'file'], $repo->read());
        $this->assertSame(1, $file->readCalls);
    }

    public function testSaveAndRefreshInvalidateRedisKeys(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['app' => 'psfs']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with($this->stringContains('psfs:config:latest:'))
            ->willReturn('psfs:config:key');
        $redis->expects($this->exactly(2))
            ->method('del');
        $this->setRedis($repo, $redis);

        $this->assertTrue($repo->save(['saved' => true]));
        $this->assertSame(1, $file->saveCalls);

        $this->setRedis($repo, null);
        $this->assertSame(['app' => 'psfs'], $repo->refresh());
        $this->assertSame(1, $file->readCalls);
    }

    public function testInvalidateHandlesRedisExceptionsWithoutThrowing(): void
    {
        $this->requireRedisExtension();
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['app' => 'psfs']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willThrowException(new \RedisException('boom'));
        $this->setRedis($repo, $redis);

        $repo->invalidate();
        $this->assertTrue(true);
    }

    public function testGetConfigPathDelegatesToFileRepository(): void
    {
        $file = new RedisTestFileConfigRepository($this->tmpConfigPath, ['app' => 'psfs']);
        $repo = new RedisReadThroughConfigRepository($file, 60, 'vtest');
        $this->assertSame($this->tmpConfigPath, $repo->getConfigPath());
    }

    private function requireRedisExtension(): void
    {
        if (!class_exists(\Redis::class)) {
            $this->markTestSkipped('ext-redis not installed');
        }
    }

    private function setRedis(RedisReadThroughConfigRepository $repo, ?\Redis $redis): void
    {
        $property = new \ReflectionProperty($repo, 'redis');
        $property->setAccessible(true);
        $property->setValue($repo, $redis);
    }
}

class RedisTestFileConfigRepository extends FileConfigRepository
{
    public int $readCalls = 0;
    public int $saveCalls = 0;
    private array $readData;

    public function __construct(string $path, array $readData)
    {
        parent::__construct($path);
        $this->readData = $readData;
    }

    public function read(): array
    {
        $this->readCalls++;
        return $this->readData;
    }

    public function save(array $data): bool
    {
        $this->saveCalls++;
        return true;
    }

    public function getFileSignature(): string
    {
        return 'mtime:hash';
    }
}
