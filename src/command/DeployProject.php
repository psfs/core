<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

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
        new InputArgument('path', InputArgument::OPTIONAL, t('Path where the Document Root will be created')),
    ))
    ->setDescription(t('Deployment command for PSFS-based projects'))
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $path = $input->getArgument('path');
        if (empty($path)) {
            $path = WEB_DIR;
        }

        GeneratorHelper::createRoot($path, $output);
        $output->writeln(str_replace('%path', $path, t("Document root regenerated at %path")));

        $cacheState = DeployHelper::refreshCacheState();
        $output->writeln(str_replace('%version', $cacheState['version'], t("Cache version updated to %version")));

        if ($cacheState['config_files_cleaned']) {
            $output->writeln(t("Configuration files cleaned successfully"));
        } else {
            $output->writeln(t("One or more files in the configuration folder could not be cleaned"));
        }

        $router = Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
        $output->writeln(t("Project routes generated successfully"));
    });
