<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\SwoolePidManager;

class SwoolePidManagerTest extends TestCase
{
    public function testReadPidReturnsNullForInvalidContent(): void
    {
        $pidFile = tempnam(sys_get_temp_dir(), 'psfs_pid_');
        if ($pidFile === false) {
            $this->fail('Unable to create temp pid file');
        }
        file_put_contents($pidFile, 'invalid');
        $this->assertNull(SwoolePidManager::readPid($pidFile));
        @unlink($pidFile);
    }

    public function testWriteAndReadPidRoundTrip(): void
    {
        $pidFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'psfs_pid_' . uniqid('', true);
        SwoolePidManager::writePid($pidFile, 12345);
        $this->assertSame(12345, SwoolePidManager::readPid($pidFile));
        SwoolePidManager::removePid($pidFile);
        $this->assertFileDoesNotExist($pidFile);
    }
}
