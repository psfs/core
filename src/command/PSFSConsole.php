<?php

namespace PSFS\command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\command\CommandContext;
use PSFS\base\command\CommandRegistry;
use PSFS\base\command\LegacyClosureCommandAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;
use RuntimeException;

/**
 * PSFS Console manager
 */
$console = new Application();
$registry = new CommandRegistry();

// Load PSFS commands
$commands = new Finder();
$commands->in(__DIR__)->notName("PSFSConsole.php");
foreach ($commands as $com) {
    if ($com->isFile()) {
        $registry->addHandler(new LegacyClosureCommandAdapter((string)$com->getRealPath()));
    }
}

// Load module commands
$domains = \PSFS\base\Router::getInstance()->getDomains();
foreach ($domains as $domain => $paths) {
    if ((false === stripos($domain, "ROOT")) && file_exists($paths['base'])) {
        $commands = new Finder();
        $commands->in($paths['base'])->path("Command")->name("*.php");
        foreach ($commands as $com) {
            if ($com->isFile()) {
                $registry->addHandler(new LegacyClosureCommandAdapter((string)$com->getRealPath()));
            }
        }
    }
}

foreach ($registry->run(new CommandContext($console)) as $result) {
    if (!$result->isSuccess()) {
        throw new RuntimeException((string)$result->getMessage());
    }
}

$console->run();
