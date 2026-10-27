<?php

namespace PSFS\base\queue;

use PSFS\base\Logger;

/**
 * Writes to the primary queue and falls back to the secondary one when the primary fails at runtime.
 * Consuming drains the primary first and then the secondary, so jobs parked during an outage are
 * processed once it is over instead of being stranded. Delivery is at-least-once and the order is
 * only guaranteed inside each backend.
 */
class FallbackJobQueue implements JobQueueInterface
{
    private bool $degradedLogged = false;

    public function __construct(
        private readonly JobQueueInterface $primary,
        private readonly JobQueueInterface $secondary
    ) {
    }

    public function enqueue(string $queue, array $payload): bool
    {
        if ($this->primary->isAvailable() && $this->primary->enqueue($queue, $payload)) {
            $this->degradedLogged = false;
            return true;
        }
        if (!$this->degradedLogged) {
            $this->degradedLogged = true;
            Logger::log('[FallbackJobQueue] primary queue unavailable, using the fallback queue', LOG_WARNING, ['queue' => $queue]);
        }
        return $this->secondary->enqueue($queue, $payload);
    }

    public function dequeue(string $queue): ?array
    {
        $message = $this->primary->isAvailable() ? $this->primary->dequeue($queue) : null;
        return $message ?? $this->secondary->dequeue($queue);
    }

    public function isAvailable(): bool
    {
        return $this->primary->isAvailable() || $this->secondary->isAvailable();
    }
}
