<?php

namespace PSFS\tests\base\queue;

use LogicException;
use PHPUnit\Framework\TestCase;
use PSFS\base\queue\JobRegistry;
use PSFS\base\queue\QueueJobInterface;

class JobRegistryTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            $this->removeDirectory($directory);
        }
        $this->tempDirectories = [];
    }

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

    public function testRegistryDiscoversJobsFromConfiguredPaths(): void
    {
        $directory = $this->createTempQueueDirectory();
        $namespace = 'PSFS\\tests\\base\\queue\\runtime' . uniqid();

        $this->writePhpFile(
            $directory,
            'DiscoveredJob.php',
            "<?php\nnamespace {$namespace};\n\nuse PSFS\\base\\queue\\QueueJobInterface;\n\nclass DiscoveredJob implements QueueJobInterface\n{\n    public static function code(): string\n    {\n        return 'runtime-discovered';\n    }\n\n    public static function fromPayload(array \$payload): self\n    {\n        return new self();\n    }\n\n    public function handle(): void\n    {\n    }\n}\n"
        );

        $registry = new JobRegistry(null, [$directory]);

        $className = $namespace . '\\DiscoveredJob';
        $this->assertTrue($registry->has('runtime-discovered'));
        $this->assertSame($className, $registry->get('runtime-discovered'));
    }

    public function testRegistryDiscoverySupportsGlobalNamespaceClassesAndPathDeduplication(): void
    {
        $directory = $this->createTempQueueDirectory();
        $className = 'RuntimeQueueJob' . uniqid();
        $code = strtolower($className);

        $this->writePhpFile(
            $directory,
            'RuntimeQueueJob.php',
            "<?php\n\nuse PSFS\\base\\queue\\QueueJobInterface;\n\nclass {$className} implements QueueJobInterface\n{\n    public static function code(): string\n    {\n        return '{$code}';\n    }\n\n    public static function fromPayload(array \$payload): self\n    {\n        return new self();\n    }\n\n    public function handle(): void\n    {\n    }\n}\n"
        );
        $this->writePhpFile($directory, 'NoClass.php', "<?php\nreturn 'noop';\n");

        $registry = new JobRegistry(null, [$directory, $directory]);

        $this->assertTrue($registry->has($code));
        $this->assertSame([$code => $className], $registry->all());
    }

    public function testRegistryDiscoveryRejectsDiscoveredClassWithoutQueueContract(): void
    {
        $directory = $this->createTempQueueDirectory();
        $namespace = 'PSFS\\tests\\base\\queue\\runtime' . uniqid();

        $this->writePhpFile(
            $directory,
            'InvalidDiscoveredJob.php',
            "<?php\nnamespace {$namespace};\n\nclass InvalidDiscoveredJob\n{\n}\n"
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement PSFS\\base\\queue\\QueueJobInterface');

        new JobRegistry(null, [$directory]);
    }

    private function createTempQueueDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs-job-registry-' . uniqid('', true);
        mkdir($directory, 0777, true);
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function writePhpFile(string $directory, string $filename, string $content): void
    {
        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $content);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            @unlink($path);
        }
        @rmdir($directory);
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
