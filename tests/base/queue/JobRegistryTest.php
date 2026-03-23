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
