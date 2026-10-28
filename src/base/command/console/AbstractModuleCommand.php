<?php

namespace PSFS\base\command\console;

use PSFS\base\Router;
use PSFS\services\migration\MigrationExecutionResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs an action (seed, migrate, rollback) over every module, or the ones matching --module
 */
abstract class AbstractModuleCommand extends Command
{
    use ConsoleFlagTrait;

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function domains(): array
    {
        return Router::getInstance()->getDomains();
    }

    /**
     * Runs the action for one module
     */
    abstract protected function runModule(string $module, string $base, InputInterface $input): MigrationExecutionResult;

    /**
     * Text printed before the module is processed
     */
    abstract protected function startMessage(string $module): string;

    /**
     * Text printed when at least one module failed
     */
    abstract protected function failureMessage(string $failures): string;

    abstract protected function successMessage(): string;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = (string)$input->getOption('module');
        $errors = [];
        foreach ($this->domains() as $domain => $paths) {
            $domain = (string)$domain;
            if (str_contains($domain, 'ROOT')) {
                continue;
            }
            $base = (string)($paths['base'] ?? '');
            $module = $this->resolveModuleName($domain, $base);
            if ($this->matchesFilter($domain, $module, $filter)) {
                $error = $this->processModule($module, $base, $input, $output);
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }
        if ([] !== $errors) {
            $output->writeln('<error>' . $this->failureMessage(implode(', ', $errors)) . '</error>');
            return self::FAILURE;
        }
        $output->writeln($this->successMessage());
        return self::SUCCESS;
    }

    /**
     * @return string|null the failure description, null when the module went well or was skipped
     */
    protected function processModule(string $module, string $base, InputInterface $input, OutputInterface $output): ?string
    {
        $output->writeln($this->startMessage($module));
        if (!file_exists($base . DIRECTORY_SEPARATOR . 'Config')) {
            $output->writeln('Module without DB configuration, skipping process for ' . $module);
            return null;
        }
        try {
            $result = $this->runModule($module, $base, $input);
            $output->writeln($result->getOutput());
            return $result->isSuccess() ? null : sprintf('%s (%s)', $module, $result->getEngine());
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return sprintf('%s (%s)', $module, $exception->getMessage());
        }
    }

    public function matchesFilter(string $domain, string $module, string $filter): bool
    {
        return '' === $filter
            || str_contains(strtolower($domain), strtolower($filter))
            || str_contains(strtolower($module), strtolower($filter));
    }

    public function resolveModuleName(string $domain, string $moduleBase): string
    {
        if ('' !== $moduleBase) {
            $candidate = basename(rtrim(realpath($moduleBase) ?: $moduleBase, DIRECTORY_SEPARATOR));
            if (!in_array($candidate, ['', '.', '..'], true)) {
                return $candidate;
            }
        }
        $module = trim($domain, '@/');
        return '' !== $module ? $module : 'module';
    }
}
