<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\config\Config;
use PSFS\base\Router;
use PSFS\base\types\helpers\DeployHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:deploy:project')
    ->setDefinition(array(
        new InputArgument('path', InputArgument::OPTIONAL, t('Path en el que crear el Document Root')),
    ))
    ->setDescription(t('Comando de despliegue de proyectos basados en PSFS'))
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $path = $input->getArgument('path');
        if (empty($path)) {
            $path = WEB_DIR;
        }

        GeneratorHelper::createRoot($path, $output);
        $output->writeln(str_replace('%path', $path, t("Document root re-generado en %path")));

        $version = DeployHelper::updateCacheVar();
        $output->writeln(str_replace('%version', $version, t("Versión de cache actualizada a %version")));

        if (Config::clearConfigFiles()) {
            $output->writeln(t("Ficheros de configuración limpiados con éxito"));
        } else {
            $output->writeln(t("No se han podido limpiar uno o más ficheros de la carpeta de configuración"));
        }

        $router = Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
        $output->writeln(t("Rutas del proyecto generadas con éxito"));
    });

