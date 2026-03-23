<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\services\migration\PhinxConfigFactory;

class PhinxConfigFactoryTest extends TestCase
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

    public function testCreateForModuleNormalizesMariaDbAndBuildsConfiguration(): void
    {
        $this->replaceConfig(array_merge($this->configBackup, [
            'client.db.vendor' => 'mariadb',
            'client.db.host' => 'db',
            'client.db.port' => 3310,
            'client.db.name' => 'client_db',
            'client.db.user' => 'user',
            'client.db.password' => 'secret',
            'migrations.phinx.environment' => 'custom_env',
            'migrations.phinx.log_table_prefix' => 'mig_',
        ]));

        $factory = new PhinxConfigFactory();
        $config = $factory->createForModule('CLIENT', '/tmp/module/Config/Migrations');

        $this->assertSame('/tmp/module/Config/Migrations', $config['paths']['migrations']);
        $this->assertSame('/tmp/module/Config/Migrations/Seeds', $config['paths']['seeds']);
        $this->assertSame('custom_env', $config['environments']['default_environment']);
        $this->assertSame('mig_client', $config['environments']['default_migration_table']);
        $this->assertArrayHasKey('custom_env', $config['environments']);
        $this->assertSame('mysql', $config['environments']['custom_env']['adapter']);
        $this->assertSame('db', $config['environments']['custom_env']['host']);
        $this->assertSame(3310, $config['environments']['custom_env']['port']);
        $this->assertSame('client_db', $config['environments']['custom_env']['name']);
        $this->assertSame('user', $config['environments']['custom_env']['user']);
        $this->assertSame('secret', $config['environments']['custom_env']['pass']);
    }

    public function testCreateForModuleNormalizesPostgresAliases(): void
    {
        $this->replaceConfig(array_merge($this->configBackup, [
            'client.db.vendor' => 'postgresql',
        ]));

        $factory = new PhinxConfigFactory();
        $config = $factory->createForModule('CLIENT', '/tmp/migrations');

        $this->assertSame('pgsql', $config['environments']['psfs']['adapter']);
    }

    private function replaceConfig(array $config): void
    {
        $instance = Config::getInstance();
        $reflection = new \ReflectionProperty($instance, 'config');
        $reflection->setAccessible(true);
        $reflection->setValue($instance, $config);
    }
}
