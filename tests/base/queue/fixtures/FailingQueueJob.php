<?php

namespace PSFS\tests\base\queue\fixtures;

use PSFS\base\queue\QueueJobInterface;

class FailingQueueJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'failing';
    }

    public static function fromPayload(array $payload): QueueJobInterface
    {
        return new self();
    }

    public function handle(): void
    {
        throw new \RuntimeException('Boom from handle');
    }
}
