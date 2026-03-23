<?php

namespace PSFS\services\migration;

use PSFS\base\config\Config;

class PhinxConfigFactory
{
    /**
     * @return array<string, mixed>
     */
    public function createForModule(string $module, string $migrationDir): array
    {
        $moduleLower = strtolower($module);
        $vendor = (string)Config::getParam('db.vendor', 'mysql', $moduleLower);
        $adapter = $this->normalizeAdapter($vendor);
        $tablePrefix = (string)Config::getParam('migrations.phinx.log_table_prefix', 'phinxlog_', $moduleLower);
        $environment = (string)Config::getParam('migrations.phinx.environment', 'psfs', $moduleLower);
        $environment = '' !== trim($environment) ? trim($environment) : 'psfs';
        $environmentConfig = [
            'adapter' => $adapter,
            'host' => (string)Config::getParam('db.host', 'localhost', $moduleLower),
            'port' => (int)Config::getParam('db.port', 3306, $moduleLower),
            'name' => (string)Config::getParam('db.name', '', $moduleLower),
            'user' => (string)Config::getParam('db.user', '', $moduleLower),
            'pass' => (string)Config::getParam('db.password', '', $moduleLower),
            'charset' => 'utf8mb4',
        ];

        return [
            'paths' => [
                'migrations' => $migrationDir,
                'seeds' => $migrationDir . DIRECTORY_SEPARATOR . 'Seeds',
            ],
            'environments' => [
                'default_migration_table' => $tablePrefix . $moduleLower,
                'default_environment' => $environment,
                $environment => $environmentConfig,
            ],
            'version_order' => 'creation',
        ];
    }

    private function normalizeAdapter(string $vendor): string
    {
        $vendor = strtolower($vendor);
        if ('mariadb' === $vendor) {
            return 'mysql';
        }
        if ('pgsql' === $vendor || 'postgres' === $vendor || 'postgresql' === $vendor) {
            return 'pgsql';
        }

        return $vendor ?: 'mysql';
    }
}
