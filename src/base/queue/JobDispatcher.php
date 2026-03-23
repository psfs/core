<?php

namespace PSFS\base\queue;

class JobDispatcher
{
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_FAILED = 'failed';

    public function __construct(private readonly JobRegistry $registry)
    {
    }

    /**
     * @param array<string, mixed> $message
     * @return array{status: string, code: ?string, reason?: string, exception?: \Throwable}
     */
    public function dispatch(array $message): array
    {
        $validation = $this->validateMessage($message);
        if (null !== $validation) {
            return $validation;
        }

        $code = (string)$message['code'];
        try {
            $jobClass = $this->registry->get($code);
            $job = $jobClass::fromPayload($message['payload']);
            $job->handle();

            return [
                'status' => self::STATUS_PROCESSED,
                'code' => $code,
            ];
        } catch (InvalidJobPayloadException|\InvalidArgumentException|\OutOfBoundsException $exception) {
            return [
                'status' => self::STATUS_INVALID,
                'code' => $code,
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => self::STATUS_FAILED,
                'code' => $code,
                'reason' => $exception->getMessage(),
                'exception' => $exception,
            ];
        }
    }

    /**
     * @param array<string, mixed> $message
     * @return array{status: string, code: ?string, reason: string}|null
     */
    private function validateMessage(array $message): ?array
    {
        $code = $message['code'] ?? null;
        $payload = $message['payload'] ?? null;

        if (!is_string($code) || '' === trim($code)) {
            return [
                'status' => self::STATUS_INVALID,
                'code' => null,
                'reason' => 'Queue message code must be a non-empty string.',
            ];
        }

        if (!is_array($payload)) {
            return [
                'status' => self::STATUS_INVALID,
                'code' => $code,
                'reason' => 'Queue message payload must be an array.',
            ];
        }

        return null;
    }
}
