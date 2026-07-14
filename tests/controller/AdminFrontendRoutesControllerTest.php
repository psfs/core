<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\exception\ApiException;
use PSFS\controller\AdminFrontendRoutesController;

class AdminFrontendRoutesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Router::dropInstance();
        Router::getInstance()->hydrateRouting();
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
        Router::dropInstance();
    }

    public function testRoutesEndpointReturnsTheRouterCatalogWithoutHtml(): void
    {
        $response = (new AdminFrontendRoutesControllerProbe())->routes();

        self::assertStringContainsString('"ok":true', $response);
        self::assertStringContainsString('"routes"', $response);
        self::assertStringNotContainsString('<html', strtolower($response));
    }

    public function testDocumentationIndexReturnsTheKnownDomainsAsAnEnvelope(): void
    {
        $response = json_decode((new AdminFrontendRoutesControllerProbe())->documentation(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertNotEmpty($response['data']['domains']);
        foreach ($response['data']['domains'] as $domain) {
            self::assertSame('/' . strtoupper($domain) . '/api/doc', $response['data']['documentPaths'][$domain]);
        }
    }

    public function testDocumentationDomainReturnsTheV2EnvelopeForAnUnknownDomain(): void
    {
        $controller = new AdminFrontendRoutesControllerProbe();
        $response = json_decode($controller->documentationDomain('domain-that-does-not-exist'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $controller->statusCode);
        self::assertSame([
            'ok' => false,
            'message' => 'Documentation domain not found',
            'data' => null,
            'errors' => ['domain' => ['Documentation domain not found']],
        ], $response);
    }

    public function testRegenerationKeepsTheExistingAuthorizationBoundary(): void
    {
        Security::setTest(false);
        Security::dropInstance();

        $this->expectException(ApiException::class);
        (new AdminFrontendRoutesControllerProbe())->regenerate();
    }
}

class AdminFrontendRoutesControllerProbe extends AdminFrontendRoutesController
{
    public int $statusCode = 200;

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }
}
