<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\AdminFrontendNavigationCatalog;

final class AdminFrontendNavigationCatalogTest extends TestCase
{
    public function testBuildsOnlyVisibleRoutesAsSpaRelativePaths(): void
    {
        $catalog = (new AdminFrontendNavigationCatalog())->build([
            'CORE' => [
                'visible' => [
                    ['slug' => 'admin-config', 'label' => 'Configuration', 'icon' => 'fa-cog'],
                    ['slug' => 'admin-core-books', 'label' => 'Books Manager', 'icon' => 'fa-database'],
                ],
                'hidden' => [
                    ['slug' => 'admin-switch-user', 'label' => 'Switch user', 'icon' => 'fa-exchange-alt'],
                ],
            ],
        ], static fn(string $slug): ?string => [
            'admin-config' => '/admin/config',
            'admin-core-books' => '/admin/core/books',
            'admin-switch-user' => '/admin/switch-user',
        ][$slug] ?? null);

        self::assertSame([
            [
                'module' => 'CORE',
                'items' => [
                    ['label' => 'Configuration', 'icon' => 'fa-cog', 'path' => '/config'],
                    ['label' => 'Books Manager', 'icon' => 'fa-database', 'path' => '/core/books'],
                ],
            ],
        ], $catalog);
    }

    public function testSkipsRoutesOutsideTheLegacyAdminMount(): void
    {
        $catalog = (new AdminFrontendNavigationCatalog())->build([
            'CORE' => [
                'visible' => [
                    ['slug' => 'invalid', 'label' => 'Invalid', 'icon' => 'fa-ban'],
                ],
            ],
        ], static fn(): string => '/api/books');

        self::assertSame([], $catalog);
    }
}
