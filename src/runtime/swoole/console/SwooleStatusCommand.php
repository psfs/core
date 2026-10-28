<?php

namespace PSFS\runtime\swoole\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleStatusCommand extends AbstractSwooleCommand
{
    public const NAME = 'psfs:swoole:status';

    protected function configure(): void
    {
        $this->setDescription('Show PSFS Swoole runtime status');
        $this->addPidFileOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->service->status($this->pidFileOptions($input), $output);
    }
}
