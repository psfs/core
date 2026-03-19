<?php

namespace PSFS\base\queue;

interface JobQueueInterface
{
    public function enqueue(string $queue, array $payload): bool;

    public function dequeue(string $queue): ?array;

    public function isAvailable(): bool;
}
