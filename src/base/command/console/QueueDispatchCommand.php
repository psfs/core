<?php

namespace PSFS\base\command\console;

use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\QueueDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * psfs:queue:dispatch: enqueues a job by its registered code
 */
class QueueDispatchCommand extends Command
{
    public const NAME = 'psfs:queue:dispatch';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, 'Queue job code to enqueue')
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue name override')
            ->addOption('payload', 'p', InputOption::VALUE_OPTIONAL, 'JSON payload for the queue job', '{}')
            ->addUsage('psfs:queue:dispatch --code=notifications --payload="{""message"":""Deploy done""}"')
            ->setDescription('Dispatch a PSFS queue job by code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = (string)$input->getOption('code');
        $queueName = (string)($input->getOption('queue') ?: $code);
        $payload = json_decode((string)$input->getOption('payload'), true);
        if ('' === $code) {
            $output->writeln('<error>Option --code is required</error>');
            return self::FAILURE;
        }
        if (!is_array($payload)) {
            $output->writeln('<error>Option --payload must be a valid JSON object/array</error>');
            return self::FAILURE;
        }
        if (!$this->dispatcher()->dispatch($code, $payload, $queueName)) {
            $output->writeln(sprintf('<error>Unable to enqueue job %s on queue %s</error>', $code, $queueName));
            return self::FAILURE;
        }
        $output->writeln(sprintf('Queued job %s on %s', $code, $queueName));
        return self::SUCCESS;
    }

    protected function dispatcher(): QueueDispatcher
    {
        return new QueueDispatcher(QueueBackendFactory::createPersistent(), new JobRegistry());
    }
}
