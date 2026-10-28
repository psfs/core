<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\SecurityHelper;

class AdminAreaRouteTest extends TestCase
{
    public static function adminRoutes(): array
    {
        return [
            'plain admin' => ['/admin'],
            'admin page' => ['/admin/config'],
            'setup admin' => ['/setup-admin'],
            'upper case' => ['/ADMIN/users'],
            'query string' => ['/admin?x=1'],
            'absolute url' => ['https://example.test/admin/config'],
            // parse_url reads "//x" as a host, so the router matches /admin/config: the gate has to as well
            'network path reference' => ['//x/admin/config'],
            'repeated slashes' => ['///admin'],
            'repeated slashes inside' => ['/admin//config'],
        ];
    }

    public static function publicRoutes(): array
    {
        return [
            'home' => ['/'],
            'module' => ['/client/api/test'],
            'admin as a later segment' => ['/x/admin'],
            // here "admin" is the host and the router matches /config, which is not an admin route
            'host named admin' => ['//admin/config'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function testAdminAreaRoutesAreRecognisedWhateverTheirShape(string $route): void
    {
        self::assertTrue(SecurityHelper::isAdminAreaRoute($route), $route);
    }

    #[DataProvider('publicRoutes')]
    public function testOtherRoutesAreNotTreatedAsAdminArea(string $route): void
    {
        self::assertFalse(SecurityHelper::isAdminAreaRoute($route), $route);
    }
}
