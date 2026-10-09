<?php

namespace PSFS\command;

use PSFS\services\MigrationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SeedDatabaseCommand
{
    /**
     * @param array<string, array<string, mixed>> $domains
     */
    public static function create(MigrationService $service, array $domains): Command
    {
        $command = new Command('psfs:seed');
        $command
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Specific module to seed')
            ->addUsage('psfs:seed')
            ->addUsage('psfs:seed --module=SHOP')
            ->setDescription('Run Phinx seeders')
            ->setCode(static function (InputInterface $input, OutputInterface $output) use ($service, $domains): int {
                $module = $input->getOption('module');
                $errors = [];

                foreach ($domains as $domain => $paths) {
                    if (str_contains($domain, 'ROOT')) {
                        continue;
                    }

                    $moduleBase = (string)($paths['base'] ?? '');
                    $resolvedModule = self::resolveModuleName((string)$domain, $moduleBase);
                    if (
                        !empty($module)
                        && !str_contains(strtolower((string)$domain), strtolower((string)$module))
                        && !str_contains(strtolower($resolvedModule), strtolower((string)$module))
                    ) {
                        continue;
                    }

                    $output->writeln(sprintf('Running seeders for module %s', $resolvedModule));
                    if (!file_exists($moduleBase . DIRECTORY_SEPARATOR . 'Config')) {
                        $output->writeln('Module without DB configuration, skipping process for ' . $resolvedModule);
                        continue;
                    }

                    try {
                        $result = $service->runSeeds($resolvedModule, $moduleBase);
                        $output->writeln($result->getOutput());
                        if (!$result->isSuccess()) {
                            $errors[] = sprintf('%s (%s)', $resolvedModule, $result->getEngine());
                        }
                    } catch (\Throwable $exception) {
                        $errors[] = sprintf('%s (%s)', $resolvedModule, $exception->getMessage());
                        $output->writeln('<error>' . $exception->getMessage() . '</error>');
                    }
                }

                if ([] !== $errors) {
                    $output->writeln('<error>Seeding failed for: ' . implode(', ', $errors) . '</error>');
                    return 1;
                }

                $output->writeln('Seeding completed');
                return 0;
            });

        return $command;
    }

    private static function resolveModuleName(string $domain, string $moduleBase): string
    {
        if ('' !== $moduleBase) {
            $moduleRealPath = realpath($moduleBase) ?: $moduleBase;
            $candidate = basename(rtrim($moduleRealPath, DIRECTORY_SEPARATOR));
            if (!in_array($candidate, ['', '.', '..'], true)) {
                return $candidate;
            }
        }

        $module = trim($domain, '@/');
        return '' !== $module ? $module : 'module';
    }
}
