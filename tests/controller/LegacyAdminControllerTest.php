<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\exception\ConfigException;
use PSFS\controller\ConfigController;
use PSFS\controller\RouteController;

class LegacyAdminControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Security::setTest(true);
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
    }

    public function testLegacyConfigParameterEndpointReturnsTheKnownParameterCatalog(): void
    {
        $response = json_decode((new LegacyConfigControllerProbe())->getConfigParams(), true, 512, JSON_THROW_ON_ERROR);

        self::assertContains('db.host', $response);
        self::assertContains('admin.front.path', $response);
    }

    public function testLegacyConfigPageKeepsTheTestGuardBeforeRenderingTemplates(): void
    {
        $this->expectException(ConfigException::class);
        (new LegacyConfigControllerProbe())->config();
    }

    public function testLegacyRoutesEndpointReturnsTheSlugCatalogAsJson(): void
    {
        $response = json_decode((new LegacyRouteControllerProbe())->getRouting(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($response);
    }
}

class LegacyConfigControllerProbe extends ConfigController
{
    public function json($response, $statusCode = 200): string
    {
        return (string)json_encode($response, JSON_UNESCAPED_SLASHES);
    }
}

class LegacyRouteControllerProbe extends RouteController
{
    public function json($response, $statusCode = 200): string
    {
        return (string)json_encode($response, JSON_UNESCAPED_SLASHES);
    }
}
