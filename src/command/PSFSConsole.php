<?php
namespace PSFS\command;

use PSFS\base\Router;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * Consola de gestión de PSFS
 */
// Load custom config
$pwd = getcwd();
$dir = $pwd . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR;

require_once $dir . 'autoload.php';


$project_path = $pwd . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR;
if (file_exists($project_path . "bootstrap.php")) {
    include_once($project_path . "bootstrap.php");
}
if (file_exists($project_path . "autoload.php")) {
    include_once($project_path . "autoload.php");
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$console = new Application();
//Hidratamos con los comandos de PSFS
$commands = new Finder();
$commands->in(__DIR__)->notName("PSFSConsole.php");
foreach ($commands as $com) if($com->isFile()) include_once($com->getRealPath());

//Hidratamos con los comandos de los módulos
$domains = Router::getInstance()->getDomains();
foreach ($domains as $domain => $paths) {
    if (!preg_match('/ROOT/i', $domain)) {
        $commands = new Finder();
        $commands->in($paths['base'])->path("Command")->name("*.php");
        foreach ($commands as $com) include_once($com->getRealPath());
    }
}

$console->run();
