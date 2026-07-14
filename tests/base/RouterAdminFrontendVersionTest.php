<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\AdminFrontendVersionRedirect;
use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\SecurityHelper;

class RouterAdminFrontendVersionTest extends TestCase
{
    private array $serverBackup;
    private array $headersBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->headersBackup = ResponseHelper::$headers_sent;
        ResponseHelper::$headers_sent = [];
        Request::dropInstance();
        Security::dropInstance();
        Security::setTest(true);
        SecurityHelper::setTest(true);
        RouterAdminFrontendAction::reset();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        ResponseHelper::$headers_sent = $this->headersBackup;
        Request::dropInstance();
        Security::dropInstance();
        Security::setTest(false);
        SecurityHelper::setTest(false);
        RouterAdminFrontendAction::reset();
    }

    public function testVersionRedirectStopsBeforeLegacyRouteMatchingAndExecution(): void
    {
        $router = new RedirectingAdminRouter();
        $router->seedRoutes([
            'GET#|#/admin/config' => [
                'method' => 'run',
                'params' => [],
                'default' => '/admin/config',
                'pattern' => '/admin/config',
                'slug' => 'admin-config',
                'label' => 'Config',
                'icon' => '',
                'module' => 'PSFS',
                'visible' => true,
                'class' => RouterAdminFrontendAction::class,
                'http' => 'GET',
                'cache' => 0,
                'requirements' => [],
            ],
        ]);
        $_SERVER = [
            'REQUEST_URI' => '/admin/config?__front=v2&tab=general',
            'QUERY_STRING' => '__front=v2&tab=general',
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'localhost',
        ];

        $this->assertSame('', $router->execute('/admin/config'));
        $this->assertSame([], RouterAdminFrontendAction::$calls);
        $this->assertSame('HTTP/1.1 302 Found', ResponseHelper::$headers_sent['http status'] ?? null);
        $this->assertSame('/admin-v2/config?tab=general', ResponseHelper::$headers_sent['location'] ?? null);
    }
}

class RedirectingAdminRouter extends Router
{
    public function __construct()
    {
    }

    public function seedRoutes(array $routes): void
    {
        $reflection = new \ReflectionClass(Router::class);
        foreach (['routing' => $routes, 'slugs' => [], 'domains' => [], 'cache' => Cache::getInstance()] as $property => $value) {
            $field = $reflection->getProperty($property);
            $field->setAccessible(true);
            $field->setValue($this, $value);
        }
        $loaded = $reflection->getProperty('loaded');
        $loaded->setAccessible(true);
        $loaded->setValue($this, true);
    }

    protected function getAdminFrontendConfiguredVersion(): mixed
    {
        return 'v2';
    }
}

class RouterAdminFrontendAction
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function run(): string
    {
        self::$calls[] = 'run';
        return 'legacy';
    }
}
