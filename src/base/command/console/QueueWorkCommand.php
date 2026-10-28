<?php

namespace PSFS\base\command\console;

use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * psfs:queue:work: consumes the jobs of a queue and runs their handlers
 */
class QueueWorkCommand extends Command
{
    use ConsoleFlagTrait;

    public const NAME = 'psfs:queue:work';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue name to consume')
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to process (0 = unlimited)', 0)
            ->addOption('idle-sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep in microseconds while waiting for jobs', 200000)
            ->addOption('stop-when-empty', null, InputOption::VALUE_OPTIONAL, 'Stop the worker when the queue is empty (1/0)', 0)
            ->addUsage('psfs:queue:work --queue=notifications --max-jobs=10 --stop-when-empty=1')
            ->setDescription('Consume PSFS queue jobs and execute their handlers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string)$input->getOption('queue');
        if ('' === $queueName) {
            $output->writeln('<error>Option --queue is required</error>');
            return self::FAILURE;
        }
        $processed = $this->worker()->work(
            $queueName,
            max(0, (int)$input->getOption('max-jobs')),
            max(1000, (int)$input->getOption('idle-sleep')),
            $this->isFlagOn($input->getOption('stop-when-empty')),
            $output
        );
        $output->writeln(sprintf('Worker completed. Processed jobs: %d', $processed));
        return self::SUCCESS;
    }

    protected function worker(): QueueWorker
    {
        return new QueueWorker(new QueueDispatcher(QueueBackendFactory::createPersistent(), new JobRegistry()));
    }
}
