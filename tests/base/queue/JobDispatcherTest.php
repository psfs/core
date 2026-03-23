<?php

namespace PSFS\tests\base\queue;

use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobDispatcher;
use PSFS\base\queue\JobRegistry;
use PSFS\tests\base\queue\fixtures\FailingQueueJob;
use PSFS\tests\base\queue\fixtures\TestQueueJob;

class JobDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        TestQueueJob::reset();
    }

    public function testDispatchExecutesValidPayload(): void
    {
        $dispatcher = new JobDispatcher(new JobRegistry([TestQueueJob::class]));

        $result = $dispatcher->dispatch([
            'code' => 'notifications',
            'payload' => ['message' => 'hello'],
        ]);

        $this->assertSame(JobDispatcher::STATUS_PROCESSED, $result['status']);
        $this->assertSame('notifications', $result['code']);
        $this->assertSame([['message' => 'hello']], TestQueueJob::$handledPayloads);
    }

    public function testDispatchRejectsInvalidPayloadShape(): void
    {
        $dispatcher = new JobDispatcher(new JobRegistry([TestQueueJob::class]));

        $result = $dispatcher->dispatch([
            'code' => 'notifications',
            'payload' => 'not-an-array',
        ]);

        $this->assertSame(JobDispatcher::STATUS_INVALID, $result['status']);
        $this->assertSame('notifications', $result['code']);
        $this->assertSame('Queue message payload must be an array.', $result['reason']);
        $this->assertSame([], TestQueueJob::$handledPayloads);
    }

    public function testDispatchMarksJobPayloadValidationErrorsAsInvalid(): void
    {
        $dispatcher = new JobDispatcher(new JobRegistry([TestQueueJob::class]));

        $result = $dispatcher->dispatch([
            'code' => 'notifications',
            'payload' => [],
        ]);

        $this->assertSame(JobDispatcher::STATUS_INVALID, $result['status']);
        $this->assertSame('notifications', $result['code']);
        $this->assertSame('Missing message payload.', $result['reason']);
    }

    public function testDispatchMarksHandleExceptionsAsFailed(): void
    {
        $dispatcher = new JobDispatcher(new JobRegistry([FailingQueueJob::class]));

        $result = $dispatcher->dispatch([
            'code' => 'failing',
            'payload' => ['anything' => true],
        ]);

        $this->assertSame(JobDispatcher::STATUS_FAILED, $result['status']);
        $this->assertSame('failing', $result['code']);
        $this->assertSame('Boom from handle', $result['reason']);
        $this->assertInstanceOf(\RuntimeException::class, $result['exception']);
    }
}
