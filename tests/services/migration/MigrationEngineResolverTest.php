<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\services\migration\MigrationEngineInterface;
use PSFS\services\migration\MigrationEngineResolver;
use PSFS\services\migration\MigrationExecutionContext;
use PSFS\services\migration\MigrationExecutionResult;

class MigrationEngineResolverTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        $this->replaceConfig($this->configBackup);
    }

    public function testResolveReturnsRequestedEngineWhenAvailable(): void
    {
        $resolver = new MigrationEngineResolver(
            new ResolverEngineStub('phinx', true),
            new ResolverEngineStub('propel', true)
        );

        $engine = $resolver->resolve('phinx', 'client');

        $this->assertSame('phinx', $engine->getName());
    }

    public function testResolveFallsBackToPropelWhenRequestedEngineIsUnavailable(): void
    {
        $this->replaceConfig(array_merge($this->configBackup, [
            'client.migrations.legacy_fallback_enabled' => true,
        ]));

        $resolver = new MigrationEngineResolver(
            new ResolverEngineStub('phinx', false),
            new ResolverEngineStub('propel', true)
        );

        $engine = $resolver->resolve('phinx', 'client');

        $this->assertSame('propel', $engine->getName());
    }

    public function testResolveThrowsWhenFallbackIsDisabled(): void
    {
        $this->replaceConfig(array_merge($this->configBackup, [
            'client.migrations.legacy_fallback_enabled' => false,
        ]));

        $resolver = new MigrationEngineResolver(
            new ResolverEngineStub('phinx', false),
            new ResolverEngineStub('propel', true)
        );

        $this->expectException(\RuntimeException::class);
        $resolver->resolve('phinx', 'client');
    }

    public function testResolveThrowsForUnknownEngine(): void
    {
        $resolver = new MigrationEngineResolver(new ResolverEngineStub('propel', true));

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve('unknown', 'client');
    }

    private function replaceConfig(array $config): void
    {
        $instance = Config::getInstance();
        $reflection = new \ReflectionProperty($instance, 'config');
        $reflection->setAccessible(true);
        $reflection->setValue($instance, $config);
    }
}

final class ResolverEngineStub implements MigrationEngineInterface
{
    public function __construct(private string $name, private bool $available)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function migrate(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return MigrationExecutionResult::success($this->name);
    }

    public function rollback(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return MigrationExecutionResult::success($this->name);
    }

    public function status(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return MigrationExecutionResult::success($this->name);
    }

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?\Propel\Generator\Manager\MigrationManager $manager = null
    ): MigrationExecutionResult {
        return MigrationExecutionResult::success($this->name);
    }
}
