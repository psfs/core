<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\types\helpers\AdminFrontendAssetsInstaller;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}

$console
    ->register('psfs:assets:install')
    ->setDescription('Publishes the bundled Admin 2.0 assets into the project document root')
    ->setCode(function (InputInterface $input, OutputInterface $output): void {
        $source = SOURCE_DIR . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin-v2';
        $destination = WEB_DIR . DIRECTORY_SEPARATOR . 'admin-v2';

        (new AdminFrontendAssetsInstaller($source, $destination))->install();
        $output->writeln('Admin 2.0 assets published at ' . $destination);
    });
