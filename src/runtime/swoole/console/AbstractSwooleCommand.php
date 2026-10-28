<?php

namespace PSFS\runtime\swoole\console;

use PSFS\runtime\swoole\SwooleCommandService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * A psfs:swoole:* command: it reads its options and delegates to the Swoole command service
 */
abstract class AbstractSwooleCommand extends Command
{
    public const DEFAULT_PID_FILE = '/tmp/psfs-swoole.pid';
    public const DEFAULT_LOG_FILE = '/tmp/psfs-swoole.log';

    public function __construct(protected readonly SwooleCommandService $service)
    {
        parent::__construct(static::NAME);
    }

    protected function addPidFileOption(): void
    {
        $this->addOption('pid-file', null, InputOption::VALUE_OPTIONAL, 'PID file path', self::DEFAULT_PID_FILE);
    }

    /**
     * @return array{pid-file: string}
     */
    protected function pidFileOptions(InputInterface $input): array
    {
        return ['pid-file' => (string)$input->getOption('pid-file')];
    }
}
