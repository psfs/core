<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\runtime\swoole\SwooleCommandService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
if (!isset($swooleCommandService) || !($swooleCommandService instanceof SwooleCommandService)) {
    $swooleCommandService = new SwooleCommandService();
}

$defaultPidFile = '/tmp/psfs-swoole.pid';
$defaultLogFile = '/tmp/psfs-swoole.log';

$console
    ->register('psfs:swoole:start')
    ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to bind', '0.0.0.0')
    ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to bind', 8080)
    ->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'Number of worker processes', 2)
    ->addOption('max-request', null, InputOption::VALUE_OPTIONAL, 'Worker max_request before recycle', 1000)
    ->addOption('daemonize', null, InputOption::VALUE_OPTIONAL, 'Run as daemon (1/0)', 0)
    ->addOption('pid-file', null, InputOption::VALUE_OPTIONAL, 'PID file path', $defaultPidFile)
    ->addOption('log-file', null, InputOption::VALUE_OPTIONAL, 'Swoole log file path', $defaultLogFile)
    ->setDescription('Start PSFS HTTP runtime using Swoole')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($swooleCommandService) {
        return $swooleCommandService->start([
            'host' => (string)$input->getOption('host'),
            'port' => (int)$input->getOption('port'),
            'workers' => (int)$input->getOption('workers'),
            'max-request' => (int)$input->getOption('max-request'),
            'daemonize' => (string)$input->getOption('daemonize'),
            'pid-file' => (string)$input->getOption('pid-file'),
            'log-file' => (string)$input->getOption('log-file'),
        ], $output);
    });

$console
    ->register('psfs:swoole:stop')
    ->addOption('pid-file', null, InputOption::VALUE_OPTIONAL, 'PID file path', $defaultPidFile)
    ->setDescription('Stop PSFS Swoole runtime')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($swooleCommandService) {
        return $swooleCommandService->stop([
            'pid-file' => (string)$input->getOption('pid-file'),
        ], $output);
    });

$console
    ->register('psfs:swoole:reload')
    ->addOption('pid-file', null, InputOption::VALUE_OPTIONAL, 'PID file path', $defaultPidFile)
    ->setDescription('Reload PSFS Swoole workers')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($swooleCommandService) {
        return $swooleCommandService->reload([
            'pid-file' => (string)$input->getOption('pid-file'),
        ], $output);
    });

$console
    ->register('psfs:swoole:status')
    ->addOption('pid-file', null, InputOption::VALUE_OPTIONAL, 'PID file path', $defaultPidFile)
    ->setDescription('Show PSFS Swoole runtime status')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($swooleCommandService) {
        return $swooleCommandService->status([
            'pid-file' => (string)$input->getOption('pid-file'),
        ], $output);
    });

$console
    ->register('psfs:swoole:check')
    ->setDescription('Validate local runtime requirements for PSFS Swoole mode')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($swooleCommandService) {
        return $swooleCommandService->check($output);
    });
