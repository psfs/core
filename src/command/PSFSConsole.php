<?php

    namespace PSFS\command;

    use Symfony\Component\Console\Application;
    use Symfony\Component\Finder\Finder;

    /**
     * Consola de gestión de PSFS
     */
    if(preg_match("/vendor/i", __DIR__)) $dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    else $dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor') . DIRECTORY_SEPARATOR;

    require_once $dir . 'autoload.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

    $console = new Application();
    //Hidratamos con los comandos de PSFS
    $commands = new Finder();
    $commands->in(__DIR__)->notName("PSFSConsole.php");
    foreach($commands as $com) include_once($com->getRealPath());

    //Hidratamos con los comandos de los módulos
    $commands = new Finder();
    $commands->in(CORE_DIR)->path("Command")->name("*.php");
    foreach($commands as $com) include_once($com->getRealPath());

    $console->run();