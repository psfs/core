<?php

namespace PSFS\runtime\swoole\console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleCheckCommand extends AbstractSwooleCommand
{
    public const NAME = 'psfs:swoole:check';

    protected function configure(): void
    {
        $this->setDescription('Validate local runtime requirements for PSFS Swoole mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->service->check($output);
    }
}
