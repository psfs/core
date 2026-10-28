<?php

namespace PSFS\command;

use PSFS\base\command\console\AbstractModuleCommand;
use PSFS\services\migration\MigrationExecutionResult;
use PSFS\services\MigrationService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * psfs:seed: runs the Phinx seeders of every module, or of the one given in --module
 */
final class SeedDatabaseCommand extends AbstractModuleCommand
{
    public const NAME = 'psfs:seed';

    /**
     * @param array<string, array<string, mixed>> $domains
     */
    public function __construct(private readonly MigrationService $service, private readonly array $domains)
    {
        parent::__construct(self::NAME);
    }

    /**
     * @param array<string, array<string, mixed>> $domains
     */
    public static function create(MigrationService $service, array $domains): self
    {
        return new self($service, $domains);
    }

    protected function configure(): void
    {
        $this
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Specific module to seed')
            ->addUsage('psfs:seed')
            ->addUsage('psfs:seed --module=SHOP')
            ->setDescription('Run Phinx seeders');
    }

    protected function domains(): array
    {
        return $this->domains;
    }

    protected function runModule(string $module, string $base, InputInterface $input): MigrationExecutionResult
    {
        return $this->service->runSeeds($module, $base);
    }

    protected function startMessage(string $module): string
    {
        return sprintf('Running seeders for module %s', $module);
    }

    protected function failureMessage(string $failures): string
    {
        return 'Seeding failed for: ' . $failures;
    }

    protected function successMessage(): string
    {
        return 'Seeding completed';
    }
}
