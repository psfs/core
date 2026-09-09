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
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
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

    public function testKnownManagerReturnsItsReadOnlyEndpointContract(): void
    {
        $router = Router::getInstance();
        $slugs = new \ReflectionProperty(Router::class, 'slugs');
        $originalSlugs = $slugs->getValue($router);
        try {
            $slugs->setValue($router, ['client-related' => 'route#/admin/CLIENT/Related']);
            $controller = new AdminFrontendManagerControllerProbe();
            $response = json_decode($controller->show('CLIENT', 'Related'), true, 512, JSON_THROW_ON_ERROR);

            self::assertTrue($response['ok'], json_encode($response));
            self::assertSame('CLIENT', $response['data']['domain']);
            self::assertSame('Related', $response['data']['api']);
            self::assertFalse($response['data']['mutation']['supported']);
            self::assertSame('/CLIENT/api/Related/{pk}', $response['data']['endpoints']['item']);
        } finally {
            $slugs->setValue($router, $originalSlugs);
        }
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
