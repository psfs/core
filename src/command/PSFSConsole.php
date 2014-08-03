<?php

    /**
     * Consola de gestión de PSFS
     */
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

    use Symfony\Component\Console\Application;
    use Symfony\Component\Finder\Finder;

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