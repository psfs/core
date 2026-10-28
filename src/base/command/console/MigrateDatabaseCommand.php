<?php

namespace PSFS\base\command\console;

use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\MigrationService;

/**
 * psfs:migrate: applies the pending database migrations
 */
class MigrateDatabaseCommand extends AbstractMigrationCommand
{
    public const NAME = 'psfs:migrate';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->addMigrationOptions();
        $this
            ->addUsage('psfs:migrate --module=TEST')
            ->addUsage('psfs:migrate --module=TEST --target=20261009123456')
            ->addUsage('psfs:migrate --simulate=1')
            ->addUsage('psfs:migrate --module=TEST --simulate=1 --engine=propel')
            ->setDescription('Run database migrations');
    }

    protected function moduleHelp(): string
    {
        return 'Specific module to migrate';
    }

    protected function targetHelp(): string
    {
        return 'Phinx migration version to apply up to';
    }

    protected function failureMessage(string $failures): string
    {
        return 'Migration failed for: ' . $failures;
    }

    protected function runAction(
        MigrationService $service,
        string $module,
        string $base,
        bool $simulate,
        ?string $engine,
        ?int $target
    ): MigrationExecutionResult {
        return $service->runMigrate($module, $base, $simulate, $engine, $target);
    }
}
