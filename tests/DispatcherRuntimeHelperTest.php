<?php

namespace PSFS\tests;

use PHPUnit\Framework\TestCase;
use PSFS\DispatcherRuntimeHelper;

class DispatcherRuntimeHelperTest extends TestCase
{
    public function testResolveTargetAndActualUris(): void
    {
        $this->assertSame('/fallback', DispatcherRuntimeHelper::resolveTargetUri(null, '/fallback'));
        $this->assertSame('/input', DispatcherRuntimeHelper::resolveTargetUri('/input', '/fallback'));

        $this->assertSame('/server', DispatcherRuntimeHelper::resolveActualRequestUri('/input', '/server'));
        $this->assertSame('/input', DispatcherRuntimeHelper::resolveActualRequestUri('/input', ''));
        $this->assertSame('/', DispatcherRuntimeHelper::resolveActualRequestUri(null, ''));
    }

    public function testFileTargetUriDetection(): void
    {
        $this->assertTrue(DispatcherRuntimeHelper::isFileTargetUri('/assets/app.css'));
        $this->assertFalse(DispatcherRuntimeHelper::isFileTargetUri('/admin/dashboard'));
    }

    public function testSetupRouteAllowlist(): void
    {
        $allowlist = ['/admin/setup', '/admin/config'];
        $this->assertTrue(DispatcherRuntimeHelper::isSetupRouteAllowed('/admin/setup/', $allowlist));
        $this->assertFalse(DispatcherRuntimeHelper::isSetupRouteAllowed('/admin/other', $allowlist));
    }
}
