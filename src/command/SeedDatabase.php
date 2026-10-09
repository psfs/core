<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\Router;
use PSFS\command\SeedDatabaseCommand;
use PSFS\services\MigrationService;
use Symfony\Component\Console\Application;

if (!isset($console)) {
    $console = new Application();
}

$console->add(SeedDatabaseCommand::create(MigrationService::getInstance(), Router::getInstance()->getDomains()));
