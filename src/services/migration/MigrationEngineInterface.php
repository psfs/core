<?php

namespace PSFS\services\migration;

use Propel\Generator\Manager\MigrationManager;

interface MigrationEngineInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    public function migrate(MigrationExecutionContext $context): MigrationExecutionResult;

    public function rollback(MigrationExecutionContext $context): MigrationExecutionResult;

    public function status(MigrationExecutionContext $context): MigrationExecutionResult;

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?MigrationManager $manager = null
    ): MigrationExecutionResult;
}
