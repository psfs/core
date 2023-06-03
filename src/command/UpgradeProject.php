<?php
namespace PSFS\Command;

use PSFS\base\Router;
use PSFS\services\GeneratorService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:update:project')
    ->setDescription(t('Actualización de configuraciones de proyectos en PSFS'))
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $modules = Router::getInstance()->getDomains();
        $output->writeln("Hay un total de " . (count($modules) - 1) . " ha actualizar");
        foreach($modules as $module => $config) {
            $clean_module = str_replace(['@', '\\', '/'], '', $module);
            if($clean_module !== 'ROOT') {
                $output->write("\t- Actualizando módulo {$clean_module}");
                GeneratorService::getInstance()->generateConfigurationTemplates($clean_module, $config['base']);
                $output->writeln("\tDONE!");
            }
        }
        $router = Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
        $output->writeln(t("Rutas del proyecto actualizadas con éxito"));
    });

