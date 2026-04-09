<?php

namespace PSFS\tests\base\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\traits\Api\ManagerTrait;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\controller\DocumentorController;
use PSFS\controller\GeneratorController;
use PSFS\controller\UserController;
use PSFS\services\AdminServices;
use PSFS\services\DocumentorService;

class CoverageControllersTest extends TestCase
{
    private array $serverBackup = [];
    private array $sessionBackup = [];
    private array $requestBackup = [];
    private array $configBackup = [];
    private string $adminsPath;
    private bool $adminsExisted = false;
    private string $adminsBackup = '';

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER ?? [];
        $this->sessionBackup = $_SESSION ?? [];
        $this->requestBackup = $_REQUEST ?? [];
        $this->configBackup = Config::getInstance()->dumpConfig();

        $this->adminsPath = CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json';
        if (file_exists($this->adminsPath)) {
            $this->adminsExisted = true;
            $this->adminsBackup = (string)file_get_contents($this->adminsPath);
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/admin/module',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_SESSION = [];
        $_REQUEST = [];

        Request::dropInstance();
        Security::dropInstance();
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);

        Request::dropInstance();
        Security::dropInstance();
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
        $_REQUEST = $this->requestBackup;

        if ($this->adminsExisted) {
            file_put_contents($this->adminsPath, $this->adminsBackup);
        } else {
            @unlink($this->adminsPath);
        }
    }

    public function testUserLocaleHelpersNormalizeAndFallbackAsExpected(): void
    {
        Config::save(['i18n.locales' => ',,,'], []);
        Config::getInstance()->loadConfigData(true);

        $normalize = new \ReflectionMethod(UserController::class, 'normalizeLocaleCode');
        $normalize->setAccessible(true);
        $extract = new \ReflectionMethod(UserController::class, 'extractAllowedAdminLocales');
        $extract->setAccessible(true);
        $resolve = new \ReflectionMethod(UserController::class, 'resolveSwitchLocale');
        $resolve->setAccessible(true);

        $this->assertNull($normalize->invoke(null, ''));
        $this->assertSame('es_ES', $normalize->invoke(null, 'es'));
        $this->assertSame('en_US', $normalize->invoke(null, 'en'));
        $this->assertSame('es_ES', $normalize->invoke(null, 'es_ES'));

        $this->assertSame(['en_US', 'es_ES'], $extract->invoke(null));

        Config::save(['i18n.locales' => 'en_US'], []);
        Config::getInstance()->loadConfigData(true);
        $this->assertSame('en_US', $resolve->invoke(null, 'es_ES', 'en_US'));
        $this->assertSame('en_US', $resolve->invoke(null, 'bad-locale', 'en_US'));
    }

    public function testUserSwitchUserDelegatesToServiceAndDeleteUsersBranches(): void
    {
        Security::setTest(true);
        Security::dropInstance();

        $controller = $this->newUserProbe();
        $service = $this->getMockBuilder(AdminServices::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['switchUser'])
            ->getMock();
        $service->method('switchUser')->willReturn('switch-ok');
        $this->setObjectProperty($controller, 'srv', $service);

        $this->assertSame('switch-ok', $controller->switchUser());

        $_REQUEST = [];
        Request::dropInstance();
        Request::getInstance()->init();
        $this->expectException(ApiException::class);
        $controller->deleteUsers();
    }

    public function testUserDeleteUsersReturnsJsonWhenUsernameProvided(): void
    {
        Security::setTest(true);
        $this->seedAdmins([
            'admin' => ['hash' => sha1('admin:admin'), 'profile' => AuthHelper::ADMIN_ID_TOKEN],
        ]);

        $_REQUEST = ['user' => 'admin'];
        Request::dropInstance();
        Request::getInstance()->init();

        $controller = $this->newUserProbe();
        $this->assertSame('OK', $controller->deleteUsers());
    }

    public function testGeneratorControllerRendersOnGetAndInvalidPost(): void
    {
        $controller = $this->newGeneratorProbe();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/module';
        Request::dropInstance();
        Request::getInstance()->init();
        $getResult = $controller->generateModule();
        $this->assertArrayHasKey('form', $getResult);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = ['admin_modules' => []];
        Request::dropInstance();
        Request::getInstance()->init();
        $postResult = $controller->doGenerateModule();
        $this->assertArrayHasKey('form', $postResult);
    }

    public function testDocumentorControllerCoversJsonHtmlDownloadAndSwaggerUi(): void
    {
        $probe = $this->newDocumentorProbe();
        $service = $this->getMockBuilder(DocumentorService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getModules', 'extractApiEndpoints', 'swaggerFormatter', 'postmanFormatter'])
            ->getMock();
        $service->method('getModules')->willReturnCallback(
            static fn (string $domain): array => $domain === 'EMPTY' ? [] : ['M']
        );
        $service->method('extractApiEndpoints')->willReturnCallback(
            static fn (array $module): array => ['endpoints' => $module]
        );
        $service->method('swaggerFormatter')->willReturnCallback(
            static fn (array $module, array $doc): array => ['swagger' => $doc]
        );
        $service->method('postmanFormatter')->willReturnCallback(
            static fn (array $module, array $doc): array => ['postman' => $doc]
        );
        $probe->setService($service);

        $probe->setRequestData(['type' => 'swagger']);
        $swagger = $probe->createApiDocs('ROOT');
        $this->assertSame(200, $swagger['status']);

        $probe->setRequestData(['type' => 'html']);
        $html = $probe->createApiDocs('ROOT');
        $this->assertSame('documentation.html.twig', $html['template']);

        $probe->setRequestData([]);
        $json = $probe->createApiDocs('ROOT');
        $this->assertSame(200, $json['status']);

        $render = $probe->swaggerUi('ROOT');
        $this->assertSame('swagger.html.twig', $render['template']);

        $this->expectException(RouterException::class);
        $probe->swaggerUi('MISSING_DOMAIN');
    }

    public function testManagerTraitThrowsForbiddenForUserRoleAndExposesMenu(): void
    {
        Security::setTest(false);
        $this->seedAdmins([
            'manager' => ['hash' => sha1('manager:pass'), 'profile' => AuthHelper::USER_ID_TOKEN],
        ]);
        Security::dropInstance();
        Security::getInstance()->updateAdmin('manager', AuthHelper::USER_ID_TOKEN);

        $probe = new ManagerTraitProbe();
        $this->assertIsArray($probe->exposeMenu());

        $this->expectException(ApiException::class);
        $probe->admin();
    }

    private function seedAdmins(array $admins): void
    {
        Cache::getInstance()->storeData($this->adminsPath, $admins, Cache::JSONGZ, true);
    }

    private function newUserProbe(): UserControllerProbe
    {
        $reflection = new \ReflectionClass(UserControllerProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function newGeneratorProbe(): GeneratorControllerProbe
    {
        $reflection = new \ReflectionClass(GeneratorControllerProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function newDocumentorProbe(): DocumentorControllerProbe
    {
        $reflection = new \ReflectionClass(DocumentorControllerProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function setObjectProperty(object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($target, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($target, $value);
    }
}

class UserControllerProbe extends UserController
{
    public function json($response, $statusCode = 200)
    {
        return $response;
    }

    public function redirect($route, array $params = [])
    {
        return '/redirect/' . $route;
    }
}

class GeneratorControllerProbe extends GeneratorController
{
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return $vars;
    }
}

class DocumentorControllerProbe extends DocumentorController
{
    private array $requestData = [];

    public function setService(DocumentorService $service): void
    {
        $this->srv = $service;
    }

    public function setRequestData(array $data): void
    {
        $this->requestData = $data;
    }

    protected function getRequest()
    {
        return new class($this->requestData) {
            public function __construct(private array $data)
            {
            }

            public function get(string $key)
            {
                return $this->data[$key] ?? null;
            }
        };
    }

    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return ['template' => $template, 'vars' => $vars];
    }

    public function json($response, $statusCode = 200)
    {
        return ['status' => $statusCode, 'payload' => $response];
    }

}

class ManagerTraitProbe
{
    use ManagerTrait;

    public function getModelTableMap()
    {
        return self::class;
    }

    public function getDomain()
    {
        return 'ROOT';
    }

    public function getApi()
    {
        return 'Demo';
    }

    public function getRoute($route = '', $absolute = false, array $params = array())
    {
        return '/mock/' . $route . '/{id}';
    }

    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return '';
    }

    public function exposeMenu(): array
    {
        return $this->getMenu();
    }
}
