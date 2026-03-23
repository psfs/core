<?php

namespace PSFS\base\queue;

class QueueDispatcher
{
    public function __construct(private readonly JobQueueInterface $queue, private readonly JobRegistry $registry)
    {
    }

    public function dispatch(string $code, array $payload = [], ?string $queueName = null, array $meta = []): bool
    {
        $this->registry->get($code);
        $envelope = array_merge([
            'code' => $code,
            'queue' => $queueName ?: $code,
            'payload' => $payload,
            'queued_at' => time(),
            'attempts' => 0,
        ], $meta);
        return $this->queue->enqueue($envelope['queue'], $envelope);
    }

    public function consume(?string $queueName = null, ?string $code = null): ?array
    {
        $targetQueue = $queueName ?: $code;
        if (empty($targetQueue)) {
            throw new \InvalidArgumentException('Queue name or code is required to consume jobs');
        }
        return $this->queue->dequeue($targetQueue);
    }

    public function execute(array $message): void
    {
        $code = (string)($message['code'] ?? '');
        $payload = $message['payload'] ?? [];
        $className = $this->registry->get($code);
        /** @var QueueJobInterface $job */
        $job = $className::fromPayload(is_array($payload) ? $payload : []);
        $job->handle();
    }
}
