<?php

namespace PSFS\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:update:project')
    ->setDescription(t('Update PSFS project configuration'))
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($console) {
        // Clean up config files...
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json')) {
            rename(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json.bak');
        }
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json')) {
            rename(CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json', CONFIG_DIR . DIRECTORY_SEPARATOR . 'urls.json.bak');
        }
        $modules = \PSFS\base\Router::getInstance()->getDomains();
        $output->writeln("There are " . (count($modules) - 1) . " modules to update");
        foreach ($modules as $module => $config) {
            $clean_module = str_replace(['@', '\\', '/'], '', $module);
            if (!in_array($clean_module, ['ROOT', 'NOSQL', 'AUTH'], true)) {
                $output->write("\t- Actualizando módulo {$clean_module}");
                $configPath = $config['base'] . DIRECTORY_SEPARATOR . 'Config';
                // Cleaning up config files and autoloader
                if(file_exists($config['base'] . DIRECTORY_SEPARATOR . 'autoloader.php')) {
                    unlink($config['base'] . DIRECTORY_SEPARATOR . 'autoloader.php');
                }
                if(file_exists($configPath . DIRECTORY_SEPARATOR . 'domains.json')) {
                    rename($configPath . DIRECTORY_SEPARATOR . 'config.php', $configPath . DIRECTORY_SEPARATOR . 'config.php.bak');
                }
                if(file_exists($configPath . DIRECTORY_SEPARATOR . 'propel.yml')) {
                    unlink($configPath . DIRECTORY_SEPARATOR . 'propel.yml');
                }
                \PSFS\services\GeneratorService::getInstance()->generateConfigurationTemplates($clean_module, $config['base']);
                $output->writeln("\tDONE!");
            }
        }
        $router = \PSFS\base\Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
        $output->writeln(t("Routes updated successfully"));
        // Run the deployment command
        $commandInput = new ArrayInput([
            'command' => 'psfs:deploy:project',
        ]);
        $console->run($commandInput, $output);
    });

