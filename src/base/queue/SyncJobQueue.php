<?php

namespace PSFS\base\queue;

class SyncJobQueue implements JobQueueInterface
{
    /**
     * @var array<string, array<int, array>>
     */
    private array $queues = [];

    public function enqueue(string $queue, array $payload): bool
    {
        if (!array_key_exists($queue, $this->queues)) {
            $this->queues[$queue] = [];
        }
        $this->queues[$queue][] = $payload;
        return true;
    }

    public function dequeue(string $queue): ?array
    {
        if (!array_key_exists($queue, $this->queues) || empty($this->queues[$queue])) {
            return null;
        }
        return array_shift($this->queues[$queue]);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function size(string $queue): int
    {
        return count($this->queues[$queue] ?? []);
    }
}
