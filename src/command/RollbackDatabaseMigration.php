<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\Router;
use PSFS\services\MigrationService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:migrate:rollback')
    ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Specific module to rollback')
    ->addOption('simulate', 's', InputOption::VALUE_OPTIONAL, 'Dry run mode (1/0)', '0')
    ->addOption('engine', 'e', InputOption::VALUE_OPTIONAL, 'Migration engine (phinx|propel)')
    ->addUsage('psfs:migrate:rollback --module=TEST')
    ->addUsage('psfs:migrate:rollback --module=TEST --engine=propel')
    ->setDescription('Rollback database migrations')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $module = $input->getOption('module');
        $simulate = in_array((string)$input->getOption('simulate'), ['1', 'true', 'yes'], true);
        $engine = $input->getOption('engine');
        $domains = Router::getInstance()->getDomains();
        $service = MigrationService::getInstance();
        $errors = [];

        foreach ($domains as $domain => $paths) {
            if (str_contains($domain, 'ROOT')) {
                continue;
            }
            $moduleBase = (string)($paths['base'] ?? '');
            $resolvedModule = '';
            if ('' !== $moduleBase) {
                $moduleRealPath = realpath($moduleBase) ?: $moduleBase;
                $candidate = basename(rtrim((string)$moduleRealPath, DIRECTORY_SEPARATOR));
                if (!in_array($candidate, ['', '.', '..'], true)) {
                    $resolvedModule = $candidate;
                }
            }
            if ('' === $resolvedModule) {
                $resolvedModule = trim((string)$domain, '@/');
            }
            if ('' === $resolvedModule) {
                $resolvedModule = 'module';
            }
            if (
                empty($module)
                || str_contains(strtolower((string)$domain), strtolower((string)$module))
                || str_contains(strtolower($resolvedModule), strtolower((string)$module))
            ) {
                $output->writeln(sprintf(t("Running migrations for module %s"), $resolvedModule));
                if (!file_exists($moduleBase . DIRECTORY_SEPARATOR . 'Config')) {
                    $output->writeln("Module without DB configuration, skipping process for " . $module);
                    continue;
                }
                try {
                    $result = $service->runRollback($resolvedModule, $moduleBase, $simulate, is_string($engine) ? $engine : null);
                    $output->writeln($result->getOutput());
                    if (!$result->isSuccess()) {
                        $errors[] = sprintf('%s (%s)', $resolvedModule, $result->getEngine());
                    }
                } catch (\Throwable $exception) {
                    $errors[] = sprintf('%s (%s)', $resolvedModule, $exception->getMessage());
                    $output->writeln('<error>' . $exception->getMessage() . '</error>');
                }
            }
        }
        if ([] !== $errors) {
            $output->writeln('<error>Rollback failed for: ' . implode(', ', $errors) . '</error>');
            return 1;
        }
        $output->writeln("Migration completed");
        return 0;
    });
