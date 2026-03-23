<?php

namespace PSFS\tests\base\queue;

use LogicException;
use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueJobInterface;

class JobRegistryTest extends TestCase
{
    public function testRegistryMapsJobsByCode(): void
    {
        $registry = new JobRegistry([TestNotificationJob::class, TestAuditJob::class], []);

        $this->assertTrue($registry->has('notifications'));
        $this->assertSame(TestNotificationJob::class, $registry->get('notifications'));
        $this->assertSame(TestAuditJob::class, $registry->get('audit'));
    }

    public function testRegistryRejectsCodeCollisions(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Queue job code collision');

        new JobRegistry([TestNotificationJob::class, DuplicateNotificationJob::class], []);
    }

    public function testGetThrowsForUnknownCode(): void
    {
        $registry = new JobRegistry([TestNotificationJob::class], []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Queue job "missing" is not registered');

        $registry->get('missing');
    }

    public function testRegistryRejectsMissingClass(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Queue job class "PSFS\\tests\\base\\queue\\MissingQueueJob" does not exist');

        new JobRegistry(['PSFS\\tests\\base\\queue\\MissingQueueJob'], []);
    }

    public function testRegistryRejectsClassThatDoesNotImplementQueueJobInterface(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement PSFS\\base\\queue\\QueueJobInterface');

        new JobRegistry([InvalidQueueJobClass::class], []);
    }

    public function testRegistryIgnoresAbstractClasses(): void
    {
        $registry = new JobRegistry([AbstractQueueJob::class, TestAuditJob::class], []);

        $this->assertFalse($registry->has('abstract-job'));
        $this->assertTrue($registry->has('audit'));
        $this->assertSame([TestAuditJob::code() => TestAuditJob::class], $registry->all());
    }

    public function testRegistryAllowsSameClassRegisteredMoreThanOnce(): void
    {
        $registry = new JobRegistry([TestNotificationJob::class, TestNotificationJob::class], []);

        $this->assertSame([TestNotificationJob::code() => TestNotificationJob::class], $registry->all());
    }
}

class TestNotificationJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): self
    {
        return new self();
    }

    public function handle(): void
    {
    }
}

class TestAuditJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'audit';
    }

    public static function fromPayload(array $payload): self
    {
        return new self();
    }

    public function handle(): void
    {
    }
}

class DuplicateNotificationJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'notifications';
    }

    public static function fromPayload(array $payload): self
    {
        return new self();
    }

    public function handle(): void
    {
    }
}

class InvalidQueueJobClass
{
}

abstract class AbstractQueueJob implements QueueJobInterface
{
    public static function code(): string
    {
        return 'abstract-job';
    }

    public static function fromPayload(array $payload): QueueJobInterface
    {
        return new TestNotificationJob();
    }
}
