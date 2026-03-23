<?php

namespace PSFS\base\queue;

class ParallelWorkerStrategy
{
    /**
     * @return array<int, array{worker: int, queue: string, maxJobs: int}>
     */
    public function plan(string $queueName, int $workers, int $maxJobs): array
    {
        $queueName = trim($queueName);
        if ('' === $queueName) {
            throw new \InvalidArgumentException('Queue name cannot be empty.');
        }

        if ($workers < 1) {
            throw new \InvalidArgumentException('Workers must be greater than zero.');
        }

        if ($maxJobs < 1) {
            throw new \InvalidArgumentException('Max jobs must be greater than zero.');
        }

        $plan = [];
        for ($worker = 1; $worker <= $workers; $worker++) {
            $plan[] = [
                'worker' => $worker,
                'queue' => $queueName,
                'maxJobs' => $maxJobs,
            ];
        }

        return $plan;
    }
}
