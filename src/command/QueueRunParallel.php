<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\queue\ParallelQueueRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:queue:run-parallel')
    ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'Queue name to consume in parallel')
    ->addOption('workers', 'w', InputOption::VALUE_OPTIONAL, 'Number of worker processes to spawn', 2)
    ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Maximum jobs per worker (0 = unlimited)', 0)
    ->addOption('idle-sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep in microseconds while waiting for jobs', 200000)
    ->addOption('stop-when-empty', null, InputOption::VALUE_OPTIONAL, 'Stop workers when queue is empty (1/0)', 1)
    ->addUsage('psfs:queue:run-parallel --queue=notifications --workers=4 --stop-when-empty=1')
    ->setDescription('Spawn multiple PSFS queue workers for the same queue')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $queueName = (string)$input->getOption('queue');
        if ('' === $queueName) {
            $output->writeln('<error>Option --queue is required</error>');
            return 1;
        }
        $runner = new ParallelQueueRunner();
        return $runner->run(
            $queueName,
            max(1, (int)$input->getOption('workers')),
            max(0, (int)$input->getOption('max-jobs')),
            max(1000, (int)$input->getOption('idle-sleep')),
            in_array((string)$input->getOption('stop-when-empty'), ['1', 'true', 'yes'], true),
            $output
        );
    });
