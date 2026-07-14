<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PSFS\runtime\swoole\UiDevelopmentProxyResolver;
use PSFS\runtime\swoole\UiDevelopmentProxyTarget;

class UiDevelopmentProxyResolverTest extends TestCase
{
    public function testResolvesExactMountAndPreservesRequestUri(): void
    {
        $target = (new UiDevelopmentProxyResolver())->resolve(
            '/ui/orders?state=open',
            '/ui',
            'http://ui:4200'
        );

        self::assertInstanceOf(UiDevelopmentProxyTarget::class, $target);
        self::assertSame('/ui', $target->mount);
        self::assertSame('http://ui:4200/ui/orders?state=open', $target->upstreamUri('/ui/orders?state=open'));
    }

    public function testDoesNotMatchPrefixCollision(): void
    {
        self::assertNull((new UiDevelopmentProxyResolver())->resolve('/uix', '/ui', 'http://ui:4200'));
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testDoesNotResolveInvalidConfiguration(mixed $mount, mixed $upstream): void
    {
        self::assertNull((new UiDevelopmentProxyResolver())->resolve('/ui/', $mount, $upstream));
    }

    public static function invalidConfigurationProvider(): array
    {
        return [
            'mount without leading slash' => ['ui', 'http://ui:4200'],
            'mount with trailing slash' => ['/ui/', 'http://ui:4200'],
            'mount with query' => ['/ui?debug=1', 'http://ui:4200'],
            'upstream with path' => ['/ui', 'http://ui:4200/app'],
            'upstream with credentials' => ['/ui', 'http://admin:secret@ui:4200'],
            'upstream without scheme' => ['/ui', 'ui:4200'],
        ];
    }
}
