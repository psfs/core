<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\QueueDispatcher;
use PSFS\base\queue\QueueWorker;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:queue:work')
    ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue name to consume')
    ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to process (0 = unlimited)', 0)
    ->addOption('idle-sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep in microseconds while waiting for jobs', 200000)
    ->addOption('stop-when-empty', null, InputOption::VALUE_OPTIONAL, 'Stop the worker when the queue is empty (1/0)', 0)
    ->addUsage('psfs:queue:work --queue=notifications --max-jobs=10 --stop-when-empty=1')
    ->setDescription('Consume PSFS queue jobs and execute their handlers')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $queueName = (string)$input->getOption('queue');
        if ('' === $queueName) {
            $output->writeln('<error>Option --queue is required</error>');
            return 1;
        }
        $worker = new QueueWorker(new QueueDispatcher(QueueBackendFactory::createPersistent(), new JobRegistry()));
        $processed = $worker->work(
            $queueName,
            max(0, (int)$input->getOption('max-jobs')),
            max(1000, (int)$input->getOption('idle-sleep')),
            in_array((string)$input->getOption('stop-when-empty'), ['1', 'true', 'yes'], true),
            $output
        );
        $output->writeln(sprintf('Worker completed. Processed jobs: %d', $processed));
        return 0;
    });
