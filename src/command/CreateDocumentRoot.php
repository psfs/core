<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:create:root')
    ->setDefinition(array(
        new InputArgument('path', InputArgument::OPTIONAL, 'Path en el que crear el Document Root'),
    ))
    ->setDescription('Comando de creación del Document Root del proyecto')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $path = $input->getArgument('path');
        if (empty($path)) $path = WEB_DIR;

        GeneratorHelper::createRoot($path, $output);

        $output->writeln("Document root generado en " . $path);
    });

