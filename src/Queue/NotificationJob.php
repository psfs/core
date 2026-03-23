<?php

namespace PSFS\Queue;

use PSFS\base\Logger;
use PSFS\base\queue\QueueJobInterface;
use PSFS\base\types\helpers\SensitiveDataHelper;

class NotificationJob implements QueueJobInterface
{
    public function __construct(private readonly array $payload)
    {
    }

    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload);
    }

    public function handle(): void
    {
        $safePayload = SensitiveDataHelper::redact($this->payload);
        Logger::log('[NotificationJob] Processing notification job', LOG_INFO, $safePayload, true);
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
