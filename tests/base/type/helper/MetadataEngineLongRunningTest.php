<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\helpers\metadata\MetadataEngine;
use PSFS\controller\ConfigController;

class MetadataEngineLongRunningTest extends TestCase
{
    private array $configBackup = [];
    private ?string $runtimeBackup = null;

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        Config::save(array_merge($this->configBackup, ['debug' => false, 'metadata.engine.swr.enabled' => true, 'metadata.engine.enabled' => true]), []);
        Config::getInstance()->loadConfigData(true);
        $this->runtimeBackup = $_SERVER[RuntimeMode::ENV_KEY] ?? null;
        $probe = new RegenerationProbe();
        $probe->drainPendingRegenerations();
    }

    protected function tearDown(): void
    {
        if (null === $this->runtimeBackup) {
            unset($_SERVER[RuntimeMode::ENV_KEY]);
        } else {
            $_SERVER[RuntimeMode::ENV_KEY] = $this->runtimeBackup;
        }
        putenv(RuntimeMode::ENV_KEY);
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testTheQueueIsNotLeftForAShutdownThatAWorkerNeverReaches(): void
    {
        RuntimeMode::enableSwoole();
        $probe = new RegenerationProbe();

        $probe->queue('key-a', ConfigController::class);

        $this->assertFalse($probe->hasRegisteredShutdown(), 'the runtime drains the queue after each response');
    }

    public function testTheSameEntryIsQueuedOnlyOnce(): void
    {
        RuntimeMode::enableSwoole();
        $probe = new RegenerationProbe();

        $probe->queue('key-b', ConfigController::class);
        $probe->queue('key-b', ConfigController::class);
        $probe->queue('key-c', ConfigController::class);
        $probe->drainPendingRegenerations();

        $this->assertSame(2, $probe->builds, 'one regeneration per entry, however many requests found it stale');
        $this->assertSame(2, $probe->releases, 'every lock is released');
    }

    public function testDrainingTwiceDoesNotRepeatTheWork(): void
    {
        RuntimeMode::enableSwoole();
        $probe = new RegenerationProbe();
        $probe->queue('key-d', ConfigController::class);

        $probe->drainPendingRegenerations();
        $probe->drainPendingRegenerations();

        $this->assertSame(1, $probe->builds);
    }
}

/**
 * A metadata engine that records what the background regeneration does, without touching the caches
 */
final class RegenerationProbe extends MetadataEngine
{
    public int $builds = 0;
    public int $releases = 0;

    public function queue(string $key, string $class): void
    {
        $this->queueBackgroundRegeneration($key, $class);
    }

    public function hasRegisteredShutdown(): bool
    {
        return (new \ReflectionProperty(MetadataEngine::class, 'shutdownRegistered'))->getValue($this);
    }

    protected function sourceSignature(\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector): string
    {
        return 'probe-signature';
    }

    protected function acquireLock(string $cacheKey): bool
    {
        return true;
    }

    protected function buildClassBundle(\ReflectionClass $reflection): array
    {
        $this->builds++;
        return ['class_tags' => [], 'method_tags' => [], 'property_nodes' => []];
    }

    protected function writeEntry(string $cacheKey, array $entry): void
    {
    }

    protected function releaseLock(string $cacheKey): void
    {
        $this->releases++;
    }
}
