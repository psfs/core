<?php

namespace PSFS\tests\base\queue\fixtures;

use PSFS\base\queue\QueueJobInterface;

class CollidingQueueJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): QueueJobInterface
    {
        return new self();
    }

    public function handle(): void
    {
    }
}
