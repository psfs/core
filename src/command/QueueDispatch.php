<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\command\console\QueueDispatchCommand;
use Symfony\Component\Console\Application;

if (!isset($console)) {
    $console = new Application();
}
$console->add(new QueueDispatchCommand());
