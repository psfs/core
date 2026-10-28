<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\runtime\swoole\console\SwooleCheckCommand;
use PSFS\runtime\swoole\console\SwooleReloadCommand;
use PSFS\runtime\swoole\console\SwooleStartCommand;
use PSFS\runtime\swoole\console\SwooleStatusCommand;
use PSFS\runtime\swoole\console\SwooleStopCommand;
use PSFS\runtime\swoole\SwooleCommandService;
use Symfony\Component\Console\Application;

if (!isset($console)) {
    $console = new Application();
}
if (!isset($swooleCommandService) || !($swooleCommandService instanceof SwooleCommandService)) {
    $swooleCommandService = new SwooleCommandService();
}

$console->add(new SwooleStartCommand($swooleCommandService));
$console->add(new SwooleStopCommand($swooleCommandService));
$console->add(new SwooleReloadCommand($swooleCommandService));
$console->add(new SwooleStatusCommand($swooleCommandService));
$console->add(new SwooleCheckCommand($swooleCommandService));
