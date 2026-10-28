<?php

namespace PSFS\runtime\swoole\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleReloadCommand extends AbstractSwooleCommand
{
    public const NAME = 'psfs:swoole:reload';

    protected function configure(): void
    {
        $this->setDescription('Reload PSFS Swoole workers');
        $this->addPidFileOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->service->reload($this->pidFileOptions($input), $output);
    }
}
