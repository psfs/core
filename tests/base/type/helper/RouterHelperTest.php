<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;

class RouterHelperTest extends TestCase
{
    public function testMatchRoutePatternDoesNotAcceptPartialSuffix(): void
    {
        $this->assertFalse(RouterHelper::matchRoutePattern('/admin/config', '/admin/confi'));
        $this->assertTrue(RouterHelper::matchRoutePattern('/admin/config', '/admin/config'));
        $this->assertTrue(RouterHelper::matchRoutePattern('/admin/config', '/admin/config/'));
    }

    public function testExtractRouteInfoReplacesApiPlaceholderInAttributeLabel(): void
    {
        $method = new \ReflectionMethod(RouterHelperAttributeFixture::class, 'admin');
        [$route, $info] = RouterHelper::extractRouteInfo($method, 'Books', 'PSFS');

        $this->assertSame('GET#|#/admin/PSFS/Books', $route);
        $this->assertSame('Books Manager', $info['label']);
        $this->assertSame('/admin/PSFS/Books', $info['default']);
    }

    public function testExtractRouteInfoUsesClassNameAsApiFallbackWhenApiMetadataMissing(): void
    {
        $method = new \ReflectionMethod(RouterHelperAttributeFixture::class, 'admin');
        [$route, $info] = RouterHelper::extractRouteInfo($method, '', 'PSFS');

        $this->assertSame('GET#|#/admin/PSFS/RouterHelperAttributeFixture', $route);
        $this->assertSame('RouterHelperAttributeFixture Manager', $info['label']);
        $this->assertSame('/admin/PSFS/RouterHelperAttributeFixture', $info['default']);
    }

    public function testExtractRouteInfoKeepsLabelWithoutTokensUntouched(): void
    {
        $method = new \ReflectionMethod(RouterHelperStaticLabelFixture::class, 'admin');
        [, $info] = RouterHelper::extractRouteInfo($method, '', 'PSFS');

        $this->assertSame('Static Manager Label', $info['label']);
    }

    public function testExtractRouteInfoTrimsApiInputBeforeReplacingTokens(): void
    {
        $method = new \ReflectionMethod(RouterHelperAttributeFixture::class, 'admin');
        [$route, $info] = RouterHelper::extractRouteInfo($method, '  Books  ', 'PSFS');

        $this->assertSame('GET#|#/admin/PSFS/Books', $route);
        $this->assertSame('Books Manager', $info['label']);
    }
}

final class RouterHelperAttributeFixture
{
    #[HttpMethod('GET')]
    #[Label('{__API__} Manager')]
    #[Route('/admin/{__DOMAIN__}/{__API__}')]
    public function admin(): void
    {
    }
}

final class RouterHelperStaticLabelFixture
{
    #[HttpMethod('GET')]
    #[Label('Static Manager Label')]
    #[Route('/admin/{__DOMAIN__}/status')]
    public function admin(): void
    {
    }
}
