<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\traits\RouteCheckTrait;

class RouteCheckTraitProbe
{
    use RouteCheckTrait;
}

class RouteCheckTraitTest extends TestCase
{
    protected function setUp(): void
    {
        RouteCheckTraitProbe::setCheckedRoute([]);
    }

    public function testCheckedRoutePreservesArrayPayload(): void
    {
        $route = ['class' => 'DemoController', 'method' => 'index'];
        RouteCheckTraitProbe::setCheckedRoute($route);

        $this->assertSame($route, RouteCheckTraitProbe::getCheckedRoute());
    }

    public function testCheckedRouteNormalizesScalarAndEmptyValues(): void
    {
        RouteCheckTraitProbe::setCheckedRoute('route-id');
        $this->assertSame(['route-id'], RouteCheckTraitProbe::getCheckedRoute());

        RouteCheckTraitProbe::setCheckedRoute('');
        $this->assertSame([], RouteCheckTraitProbe::getCheckedRoute());

        RouteCheckTraitProbe::setCheckedRoute(null);
        $this->assertSame([], RouteCheckTraitProbe::getCheckedRoute());
    }
}
