<?php

namespace PSFS\command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * PSFS Console manager
 */
$console = new Application();
//Hidratamos con los comandos de PSFS
$commands = new Finder();
$commands->in(__DIR__)->notName("PSFSConsole.php");
foreach ($commands as $com) if ($com->isFile()) include_once($com->getRealPath());

//Hidratamos con los comandos de los módulos
$domains = \PSFS\base\Router::getInstance()->getDomains();
foreach ($domains as $domain => $paths) {
    if ((false === stripos($domain, "ROOT")) && file_exists($paths['base'])) {
        $commands = new Finder();
        $commands->in($paths['base'])->path("Command")->name("*.php");
        foreach ($commands as $com) {
            if ($com->isFile()) {
                include_once($com->getRealPath());
            }
        }
    }
}

$console->run();
