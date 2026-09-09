<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
use PSFS\base\admin\AdminFrontendCsrf;
use PSFS\base\exception\ApiException;
use PSFS\controller\AdminFrontendRoutesController;

class AdminFrontendRoutesControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Request::dropInstance();
        Router::dropInstance();
        Router::getInstance()->hydrateRouting();
    }

    protected function tearDown(): void
    {
        Security::setTest(false);
        Security::dropInstance();
        Request::dropInstance();
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
        self::assertIsArray($response['data']['domains']);
        self::assertIsArray($response['data']['documentPaths']);

        $documentPathDomains = array_keys($response['data']['documentPaths']);
        sort($documentPathDomains, SORT_NATURAL | SORT_FLAG_CASE);
        self::assertSame($documentPathDomains, $response['data']['domains']);
        foreach ($response['data']['domains'] as $domain) {
            self::assertSame('/' . strtoupper($domain) . '/api/doc', $response['data']['documentPaths'][$domain]);
        }
    }

    public function testDocumentationIndexNormalizesNonRootDomainNames(): void
    {
        $router = Router::getInstance();
        $domains = new \ReflectionProperty(Router::class, 'domains');
        $originalDomains = $domains->getValue($router);
        try {
            $domains->setValue($router, ['@ROOT/' => [], '@CLIENT/' => []]);
            $response = json_decode((new AdminFrontendRoutesControllerProbe())->documentation(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(['client'], $response['data']['domains']);
            self::assertSame('/CLIENT/api/doc', $response['data']['documentPaths']['client']);
        } finally {
            $domains->setValue($router, $originalDomains);
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

    public function testDocumentationDomainUsesTheCanonicalDomainNameForKnownDomains(): void
    {
        $router = Router::getInstance();
        $domains = new \ReflectionProperty(Router::class, 'domains');
        $originalDomains = $domains->getValue($router);
        try {
            $domains->setValue($router, ['@CLIENT/' => []]);
            $controller = new AdminFrontendRoutesControllerProbe();
            $response = json_decode($controller->documentationDomain('CLIENT'), true, 512, JSON_THROW_ON_ERROR);

            self::assertTrue($response['ok'], json_encode($response));
            self::assertArrayHasKey('paths', $response['data']);
        } finally {
            $domains->setValue($router, $originalDomains);
        }
    }

    public function testCanonicalDomainNameFallsBackToTheRequestedValueWhenNoAliasMatches(): void
    {
        $controller = new AdminFrontendRoutesControllerProbe();
        $method = new \ReflectionMethod(AdminFrontendRoutesController::class, 'canonicalDomainName');

        self::assertSame('MISSING', $method->invoke($controller, ['@CLIENT/' => []], 'MISSING'));
    }

    public function testRegenerationWithValidCsrfTokenKeepsTheExistingAuthorizationBoundary(): void
    {
        Security::setTest(false);
        Security::dropInstance();
        $token = AdminFrontendCsrf::issue();
        Request::dropInstance();
        Request::getInstance()->setServer([
            'HTTP_X_PSFS_CSRF' => $token,
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Restricted area');
        (new AdminFrontendRoutesControllerProbe())->regenerate();
    }

    public function testRegenerationRejectsAMissingCsrfTokenBeforeRouteWork(): void
    {
        Security::setTest(false);
        Security::dropInstance();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid CSRF token');
        (new AdminFrontendRoutesControllerProbe())->regenerate();
    }

    public function testRegenerationReturnsTheSuccessEnvelopeWhenTheRouterCanRebuild(): void
    {
        Security::setTest(true);

        $response = json_decode((new AdminFrontendRoutesControllerProbe())->regenerate(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok'], json_encode($response));
        self::assertTrue($response['data']['regenerated']);
    }

    public function testRegenerationReturnsA500EnvelopeWhenRouteHydrationFails(): void
    {
        Security::setTest(true);
        $this->injectRouter(new AdminFrontendRoutesFailureRouter());
        $controller = new AdminFrontendRoutesControllerProbe();

        $response = json_decode($controller->regenerate(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertSame('route rebuild failed', $response['errors']['routes'][0]);
    }

    private function injectRouter(Router $router): void
    {
        $property = new \ReflectionProperty(SingletonRegistry::class, 'instances');
        $instances = $property->getValue();
        $context = $_SERVER[SingletonRegistry::CONTEXT_SESSION] ?? SingletonRegistry::CONTEXT_SESSION;
        $instances[$context][Router::class] = $router;
        $property->setValue(null, $instances);
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

class AdminFrontendRoutesFailureRouter extends Router
{
    public function hydrateRouting()
    {
        throw new \RuntimeException('route rebuild failed');
    }

    public function simpatize()
    {
    }
}
