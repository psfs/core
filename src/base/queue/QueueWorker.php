<?php

namespace PSFS\base\queue;

use PSFS\base\Logger;
use Symfony\Component\Console\Output\OutputInterface;

class QueueWorker
{
    public function __construct(private readonly QueueDispatcher $dispatcher)
    {
    }

    public function work(
        string $queueName,
        int $maxJobs = 0,
        int $idleSleepUs = 200000,
        bool $stopWhenEmpty = false,
        ?OutputInterface $output = null
    ): int {
        $processed = 0;
        while (0 === $maxJobs || $processed < $maxJobs) {
            $message = $this->dispatcher->consume($queueName);
            if (null === $message) {
                if ($stopWhenEmpty) {
                    break;
                }
                usleep(max(1000, $idleSleepUs));
                continue;
            }
            try {
                $this->dispatcher->execute($message);
                $processed++;
                if (null !== $output) {
                    $output->writeln(sprintf('[queue] processed job %s on %s', $message['code'] ?? 'unknown', $queueName));
                }
            } catch (\Throwable $exception) {
                Logger::log('[QueueWorker] Job execution failed: ' . $exception->getMessage(), LOG_ERR, [
                    'queue' => $queueName,
                    'code' => $message['code'] ?? null,
                ]);
                if (null !== $output) {
                    $output->writeln(sprintf('<error>[queue] failed job %s on %s: %s</error>', $message['code'] ?? 'unknown', $queueName, $exception->getMessage()));
                }
            }
        }
        return $processed;
    }
}
