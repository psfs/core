<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\UiDevelopmentProxyResolver;
use PSFS\runtime\swoole\UiDevelopmentWebSocketBridge;

class UiDevelopmentWebSocketBridgeTest extends TestCase
{
    public function testResolvesTheAdminMountForTheHmrWebSocket(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $previousQuery = $_SERVER['QUERY_STRING'] ?? null;
        $previousUpstream = getenv('ADMIN_UI_DEV_UPSTREAM');
        $_SERVER['REQUEST_URI'] = '/admin-v2/?token=hmr';
        $_SERVER['QUERY_STRING'] = 'token=hmr';
        putenv('ADMIN_UI_DEV_UPSTREAM=http://ui:4200');

        try {
            $bridge = new UiDevelopmentWebSocketBridge(resolver: new UiDevelopmentProxyResolver());
            $method = new \ReflectionMethod($bridge, 'resolveTarget');
            $target = $method->invoke($bridge);

            self::assertNotNull($target);
            self::assertSame('/admin-v2', $target->mount);
            self::assertSame('http://ui:4200/admin-v2/?token=hmr', $target->upstreamUri('/admin-v2/?token=hmr'));
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
            if ($previousQuery === null) {
                unset($_SERVER['QUERY_STRING']);
            } else {
                $_SERVER['QUERY_STRING'] = $previousQuery;
            }
            putenv($previousUpstream === false ? 'ADMIN_UI_DEV_UPSTREAM' : 'ADMIN_UI_DEV_UPSTREAM=' . $previousUpstream);
        }
    }
}
