<?php

namespace PSFS\base\queue;

interface QueueJobInterface
{
    public static function code(): string;

    public static function fromPayload(array $payload): self;

    public function handle(): void;
}
