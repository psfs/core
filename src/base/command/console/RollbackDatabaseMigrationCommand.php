<?php

namespace PSFS\base\command\console;

use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\MigrationService;

/**
 * psfs:migrate:rollback: reverts database migrations
 */
class RollbackDatabaseMigrationCommand extends AbstractMigrationCommand
{
    public const NAME = 'psfs:migrate:rollback';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addMigrationOptions();
        $this
            ->addUsage('psfs:migrate:rollback --module=TEST')
            ->addUsage('psfs:migrate:rollback --module=TEST --target=20261009123456')
            ->addUsage('psfs:migrate:rollback --module=TEST --engine=propel')
            ->setDescription('Rollback database migrations');
    }

    protected function moduleHelp(): string
    {
        return 'Specific module to rollback';
    }

    protected function targetHelp(): string
    {
        return 'Phinx migration version to rollback to';
    }

    protected function failureMessage(string $failures): string
    {
        return 'Rollback failed for: ' . $failures;
    }

    protected function runAction(
        MigrationService $service,
        string $module,
        string $base,
        bool $simulate,
        ?string $engine,
        ?int $target
    ): MigrationExecutionResult {
        return $service->runRollback($module, $base, $simulate, $engine, $target);
    }
}
