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
    ->register('psfs:migrate:rollback')
    ->addOption('module', 'm', InputArgument::OPTIONAL, 'Módulo específico a deshacer')
    ->addUsage('psfs:migrate:rollback --module=TEST')
    ->setDescription('Deshacer las migraciones de base de datos')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $module = $input->getOption('module');
        $domains = Router::getInstance()->getDomains();
        foreach($domains as $domain => $paths) {
            if(str_contains($domain, 'ROOT')) {
                continue;
            }
            if(empty($module) || str_contains(strtolower($domain), strtolower($module))) {
                $output->writeln(sprintf(t("Ejecutando migraciones para módulo %s"), $domain));
                $configDir = realpath($paths['base']) . DIRECTORY_SEPARATOR . 'Config';
                $migrationDir = $configDir . DIRECTORY_SEPARATOR . 'Migrations';
                $script = sprintf("%s/bin/propel migration:down  --config-dir=%s --output-dir=%s", realpath(VENDOR_DIR), $configDir, $migrationDir);
                $result = shell_exec($script);
                $output->writeln($result);
            }
        }
        $output->writeln("Migración finalizada");
    });

