<?php

namespace PSFS\tests\base\queue\fixtures;

use PSFS\base\queue\InvalidJobPayloadException;
use PSFS\base\queue\QueueJobInterface;

class TestQueueJob implements QueueJobInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public static array $handledPayloads = [];

    public function __construct(private readonly array $payload)
    {
    }

    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): QueueJobInterface
    {
        if (!array_key_exists('message', $payload) || !is_string($payload['message'])) {
            throw new InvalidJobPayloadException('Missing message payload.');
        }

        return new self($payload);
    }

    public function handle(): void
    {
        self::$handledPayloads[] = $this->payload;
    }

    public static function reset(): void
    {
        self::$handledPayloads = [];
    }
}
