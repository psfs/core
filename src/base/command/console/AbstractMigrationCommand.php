<?php

namespace PSFS\base\command\console;

use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\MigrationService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Options and validation shared by psfs:migrate and psfs:migrate:rollback
 */
abstract class AbstractMigrationCommand extends AbstractModuleCommand
{
    abstract protected function targetHelp(): string;

    abstract protected function moduleHelp(): string;

    abstract protected function runAction(
        MigrationService $service,
        string $module,
        string $base,
        bool $simulate,
        ?string $engine,
        ?int $target
    ): MigrationExecutionResult;

    protected function service(): MigrationService
    {
        return MigrationService::getInstance();
    }

    protected function addMigrationOptions(): void
    {
        $this
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, $this->moduleHelp())
            ->addOption('simulate', 's', InputOption::VALUE_OPTIONAL, 'Dry run mode (1/0)', '0')
            ->addOption('engine', 'e', InputOption::VALUE_OPTIONAL, 'Migration engine (phinx|propel)')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, $this->targetHelp());
    }

    protected function startMessage(string $module): string
    {
        return sprintf(t('Running migrations for module %s'), $module);
    }

    protected function successMessage(): string
    {
        return 'Migration completed';
    }

    protected function runModule(string $module, string $base, InputInterface $input): MigrationExecutionResult
    {
        $engine = $input->getOption('engine');
        return $this->runAction(
            $this->service(),
            $module,
            $base,
            $this->isFlagOn($input->getOption('simulate')),
            is_string($engine) ? $engine : null,
            $this->parseTarget($input->getOption('target'))
        );
    }

    /**
     * @throws \InvalidArgumentException when the target is not a non-negative integer
     */
    public function parseTarget(mixed $target): ?int
    {
        if (null === $target) {
            return null;
        }
        if (!ctype_digit((string)$target)) {
            throw new \InvalidArgumentException('Migration target must be a non-negative integer version');
        }
        return (int)$target;
    }
}
