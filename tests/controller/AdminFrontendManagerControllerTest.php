<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\controller\AdminFrontendManagerController;

class AdminFrontendManagerControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Security::setTest(true);
        Router::dropInstance();
        Router::getInstance()->hydrateRouting();
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
        Router::dropInstance();
    }

    public function testRejectsInvalidManagerSegmentsBeforeBuildingUrls(): void
    {
        $this->expectException(\PSFS\base\exception\ApiException::class);
        (new AdminFrontendManagerControllerProbe())->show('../CLIENT', 'Related');
    }

    public function testMissingManagerReturnsStructuredNotFound(): void
    {
        $controller = new AdminFrontendManagerControllerProbe();
        $response = json_decode($controller->show('CLIENT', 'Missing'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertArrayHasKey('manager', $response['errors']);
    }

    public function testKnownManagerReturnsTheReadOnlyContract(): void
    {
        $router = Router::getInstance();
        $slugs = $router->getSlugs();
        $slugs['known-manager'] = 'GET#|#/admin/CLIENT/Related';
        $slugsProperty = new \ReflectionProperty(Router::class, 'slugs');
        $slugsProperty->setValue($router, $slugs);

        $controller = new AdminFrontendManagerControllerProbe();
        $response = json_decode($controller->show('CLIENT', 'Related'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $controller->statusCode);
        self::assertTrue($response['ok']);
        self::assertSame('CLIENT', $response['data']['domain']);
        self::assertSame('Related', $response['data']['api']);
        self::assertSame('/CLIENT/api/Related', $response['data']['endpoints']['list']);
        self::assertFalse($response['data']['mutation']['supported']);
        self::assertSame('__combo', $response['data']['query']['combo']);
    }
}

class AdminFrontendManagerControllerProbe extends AdminFrontendManagerController
{
    public int $statusCode = 200;

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }
}
