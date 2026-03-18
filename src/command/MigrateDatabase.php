<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\Router;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:migrate')
    ->addOption('module', 'm', InputArgument::OPTIONAL, 'Specific module to migrate')
    ->addOption('simulate', 's', InputArgument::OPTIONAL, 'Only write in the migration table', false)
    ->addUsage('psfs:migrate --module=TEST')
    ->addUsage('psfs:migrate --simulate=1')
    ->addUsage('psfs:migrate --module=TEST --simulate=1')
    ->setDescription('Run database migrations')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $module = $input->getOption('module');
        $simulate = $input->getOption('simulate') !== false ? '--fake' : '';
        $domains = Router::getInstance()->getDomains();
        foreach($domains as $domain => $paths) {
            if(str_contains($domain, 'ROOT')) {
                continue;
            }
            if(empty($module) || str_contains(strtolower($domain), strtolower($module))) {
                $output->writeln(sprintf(t("Running migrations for module %s"), $domain));
                $configDir = realpath($paths['base']) . DIRECTORY_SEPARATOR . 'Config';
                $migrationDir = $configDir . DIRECTORY_SEPARATOR . 'Migrations';
                if(file_exists($configDir)) {
                    $script = sprintf("%s/bin/propel migrate %s --platform mysql --config-dir=%s --output-dir=%s", realpath(VENDOR_DIR), $simulate, $configDir, $migrationDir);
                    $result = shell_exec($script);
                    $output->writeln($result);
                } else {
                    $output->writeln("Module without DB configuration, skipping process for " . $module);
                }
            }
        }
        $output->writeln("Migration completed");
    });
