<?php

namespace PSFS\services\migration;

use Propel\Generator\Manager\MigrationManager;

class PropelMigrationEngine implements MigrationEngineInterface
{
    public function __construct(private readonly CommandRunner $runner)
    {
    }

    public function getName(): string
    {
        return 'propel';
    }

    public function isAvailable(): bool
    {
        $binary = $this->getBinaryPath();
        return is_file($binary) || is_executable($binary);
    }

    public function migrate(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $simulate = $context->isSimulate() ? '--fake' : '';
        $command = sprintf(
            '%s migrate %s --platform mysql --config-dir=%s --output-dir=%s',
            escapeshellarg($this->getBinaryPath()),
            $simulate,
            escapeshellarg($context->getConfigDir()),
            escapeshellarg($context->getMigrationDir())
        );

        return $this->run($command);
    }

    public function rollback(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $command = sprintf(
            '%s migration:down --config-dir=%s --output-dir=%s',
            escapeshellarg($this->getBinaryPath()),
            escapeshellarg($context->getConfigDir()),
            escapeshellarg($context->getMigrationDir())
        );

        return $this->run($command);
    }

    public function status(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $command = sprintf(
            '%s migration:status --config-dir=%s --output-dir=%s',
            escapeshellarg($this->getBinaryPath()),
            escapeshellarg($context->getConfigDir()),
            escapeshellarg($context->getMigrationDir())
        );

        return $this->run($command);
    }

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?MigrationManager $manager = null
    ): MigrationExecutionResult {
        if (null === $manager) {
            return MigrationExecutionResult::failure($this->getName(), 'MigrationManager is required for Propel generation');
        }
        $migrationFileName = $manager->getMigrationFileName($timestamp);
        $migrationClassBody = $manager->getMigrationClassBody($migrationsUp, $migrationsDown, $timestamp);
        $target = $migrationDir . DIRECTORY_SEPARATOR . $migrationFileName;
        file_put_contents($target, $migrationClassBody);

        return MigrationExecutionResult::success($this->getName(), sprintf('Generated legacy migration: %s', $target));
    }

    private function run(string $command): MigrationExecutionResult
    {
        $result = $this->runner->run($command);
        if (0 === $result['exit_code']) {
            return MigrationExecutionResult::success($this->getName(), $result['output'], $command);
        }

        return MigrationExecutionResult::failure($this->getName(), $result['output'], $result['exit_code'], $command);
    }

    private function getBinaryPath(): string
    {
        return VENDOR_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'propel';
    }
}
