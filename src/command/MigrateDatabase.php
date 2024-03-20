<?php

namespace PSFS\Command;

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
    ->addOption('module', 'm', InputArgument::OPTIONAL, 'Módulo específico a migrar')
    ->addOption('simulate', 's', InputArgument::OPTIONAL, 'Sólo graba en la tabla de migración', false)
    ->addUsage('psfs:migrate --module=TEST')
    ->addUsage('psfs:migrate --simulate=1')
    ->addUsage('psfs:migrate --module=TEST --simulate=1')
    ->setDescription('Ejecutar las migraciones de base de datos')
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
                $output->writeln(sprintf(t("Ejecutando migraciones para módulo %s"), $domain));
                $configDir = realpath($paths['base']) . DIRECTORY_SEPARATOR . 'Config';
                $migrationDir = $configDir . DIRECTORY_SEPARATOR . 'Migrations';
                if(file_exists($configDir)) {
                    $script = sprintf("%s/bin/propel migrate %s --platform mysql --config-dir=%s --output-dir=%s", realpath(VENDOR_DIR), $simulate, $configDir, $migrationDir);
                    $result = shell_exec($script);
                    $output->writeln($result);
                } else {
                    $output->writeln("Módulo sin configuración de BD, saltando proceso para " . $module);
                }
            }
        }
        $output->writeln("Migración finalizada");
    });

