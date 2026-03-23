<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueBackendFactory;
use PSFS\base\queue\QueueDispatcher;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:queue:dispatch')
    ->addOption('code', 'c', InputOption::VALUE_REQUIRED, 'Queue job code to enqueue')
    ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue name override')
    ->addOption('payload', 'p', InputOption::VALUE_OPTIONAL, 'JSON payload for the queue job', '{}')
    ->addUsage('psfs:queue:dispatch --code=notifications --payload="{""message"":""Deploy done""}"')
    ->setDescription('Dispatch a PSFS queue job by code')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $code = (string)$input->getOption('code');
        $queueName = (string)($input->getOption('queue') ?: $code);
        $payload = json_decode((string)$input->getOption('payload'), true);
        if ('' === $code) {
            $output->writeln('<error>Option --code is required</error>');
            return 1;
        }
        if (!is_array($payload)) {
            $output->writeln('<error>Option --payload must be a valid JSON object/array</error>');
            return 1;
        }
        $dispatcher = new QueueDispatcher(QueueBackendFactory::createPersistent(), new JobRegistry());
        $queued = $dispatcher->dispatch($code, $payload, $queueName);
        if (!$queued) {
            $output->writeln(sprintf('<error>Unable to enqueue job %s on queue %s</error>', $code, $queueName));
            return 1;
        }
        $output->writeln(sprintf('Queued job %s on %s', $code, $queueName));
        return 0;
    });
