<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\AdminFrontendVersionResolver;

class AdminFrontendVersionResolverTest extends TestCase
{
    private AdminFrontendVersionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AdminFrontendVersionResolver();
    }

    public function testDefaultsToLegacyWithoutAnOverride(): void
    {
        $this->assertNull($this->resolver->resolve('GET', '/admin/config?tab=general', 'legacy'));
        $this->assertNull($this->resolver->resolve('GET', '/admin/config?tab=general', 'invalid'));
    }

    public function testConfiguredV2RedirectsAndPreservesFunctionalQueryParameters(): void
    {
        $redirect = $this->resolver->resolve('GET', '/admin/demo/users?__page=2&filter=active', 'v2');

        $this->assertNotNull($redirect);
        $this->assertSame('/admin-v2/demo/users?__page=2&filter=active', $redirect->location);
        $this->assertSame(302, $redirect->statusCode);
    }

    public function testAdminRootRedirectsToTheSpaBaseHref(): void
    {
        $redirect = $this->resolver->resolve('GET', '/admin?__front=v2', 'legacy');

        $this->assertSame('/admin-v2/', $redirect?->location);
    }

    public function testConfiguredFrontendMountIsUsedForTheVersionedRedirect(): void
    {
        $redirect = $this->resolver->resolve('GET', '/admin/config', 'v2', '/back-office');

        $this->assertSame('/back-office/config', $redirect?->location);
    }

    public function testQueryOverrideHasPrecedenceAndIsRemovedFromTheRedirect(): void
    {
        $forcedV2 = $this->resolver->resolve('GET', '/admin/config?__front=v2&tab=general', 'legacy');
        $forcedLegacy = $this->resolver->resolve('GET', '/admin/config?__front=legacy&tab=general', 'v2');

        $this->assertSame('/admin-v2/config?tab=general', $forcedV2?->location);
        $this->assertNull($forcedLegacy);
    }

    public function testDoesNotRedirectAlreadyVersionedTechnicalOrMutableRequests(): void
    {
        foreach ([
            ['GET', '/admin-v2/config'],
            ['POST', '/admin/config'],
            ['PUT', '/admin/setup'],
            ['GET', '/admin/login'],
            ['GET', '/admin/locale/es_ES'],
            ['GET', '/admin/config/params'],
            ['GET', '/admin/routes/show'],
            ['GET', '/admin/api/v2/bootstrap'],
            ['GET', '/admin/demo/swagger-ui'],
            ['GET', '/admin/assets/admin.css'],
        ] as [$method, $uri]) {
            $this->assertNull($this->resolver->resolve($method, $uri, 'v2'), $uri);
        }
    }

    public function testIgnoresInvalidOverrideAndSupportsHeadRequests(): void
    {
        $redirect = $this->resolver->resolve('HEAD', '/admin/module?__front=unknown', 'v2');

        $this->assertSame('/admin-v2/module', $redirect?->location);
    }
}
