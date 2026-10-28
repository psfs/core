<?php

namespace PSFS\runtime\swoole\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleStopCommand extends AbstractSwooleCommand
{
    public const NAME = 'psfs:swoole:stop';

    protected function configure(): void
    {
        $this->setDescription('Stop PSFS Swoole runtime');
        $this->addPidFileOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->service->stop($this->pidFileOptions($input), $output);
    }
}
