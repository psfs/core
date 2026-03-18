<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\RouterHelper;

class RouterHelperTest extends TestCase
{
    public function testMatchRoutePatternDoesNotAcceptPartialSuffix(): void
    {
        $this->assertFalse(RouterHelper::matchRoutePattern('/admin/config', '/admin/confi'));
        $this->assertTrue(RouterHelper::matchRoutePattern('/admin/config', '/admin/config'));
        $this->assertTrue(RouterHelper::matchRoutePattern('/admin/config', '/admin/config/'));
    }
}

