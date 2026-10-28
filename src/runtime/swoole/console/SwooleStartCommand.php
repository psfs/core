<?php

namespace PSFS\runtime\swoole\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleStartCommand extends AbstractSwooleCommand
{
    public const NAME = 'psfs:swoole:start';

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to bind', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to bind', 8080)
            ->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'Number of worker processes', 2)
            ->addOption('max-request', null, InputOption::VALUE_OPTIONAL, 'Worker max_request before recycle', 1000)
            ->addOption('daemonize', null, InputOption::VALUE_OPTIONAL, 'Run as daemon (1/0)', 0)
            ->addOption('log-file', null, InputOption::VALUE_OPTIONAL, 'Swoole log file path', self::DEFAULT_LOG_FILE)
            ->setDescription('Start PSFS HTTP runtime using Swoole');
        $this->addPidFileOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->service->start([
            'host' => (string)$input->getOption('host'),
            'port' => (int)$input->getOption('port'),
            'workers' => (int)$input->getOption('workers'),
            'max-request' => (int)$input->getOption('max-request'),
            'daemonize' => (string)$input->getOption('daemonize'),
            'log-file' => (string)$input->getOption('log-file'),
        ] + $this->pidFileOptions($input), $output);
    }
}
