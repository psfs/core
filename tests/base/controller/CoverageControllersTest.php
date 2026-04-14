<?php

namespace PSFS\tests\base\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\config\AdminForm;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\RouterException;
use PSFS\base\types\Form;
use PSFS\base\types\traits\Api\ManagerTrait;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\controller\ConfigController;
use PSFS\controller\DocumentorController;
use PSFS\controller\GeneratorController;
use PSFS\controller\RouteController;
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

    public function testGeneratorControllerCoversValidAndExceptionPaths(): void
    {
        Security::setTest(true);
        $controller = $this->newGeneratorProbe();
        $generatorService = $this->getMockBuilder(\PSFS\services\GeneratorService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createStructureModule'])
            ->getMock();
        $this->setObjectProperty($controller, 'gen', $generatorService);

        $moduleForm = new ModuleForm();
        $moduleForm->build();
        $requestData = $this->buildRequestPayloadFromForm($moduleForm, [
            'module' => 'demo',
            'controllerType' => 'normal',
            'api' => '',
        ]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = [$moduleForm->getName() => $requestData];
        Request::dropInstance();
        Request::getInstance()->init();

        $result = $controller->doGenerateModule();
        $this->assertArrayHasKey('form', $result);

        $generatorService->method('createStructureModule')
            ->willThrowException(new \RuntimeException('boom'));
        $_REQUEST = [$moduleForm->getName() => $requestData];
        Request::dropInstance();
        Request::getInstance()->init();
        $errorResult = $controller->doGenerateModule();
        $this->assertArrayHasKey('form', $errorResult);
    }

    public function testConfigControllerSaveConfigWithValidCsrfPayload(): void
    {
        Security::setTest(true);
        $controller = $this->newConfigProbe();

        $form = new ConfigForm(
            '/admin/config',
            Config::$required,
            Config::$optional,
            Config::getInstance()->dumpConfig()
        );
        $form->build();
        $payload = $this->buildRequestPayloadFromForm($form);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST = [$form->getName() => $payload];
        Request::dropInstance();
        Request::getInstance()->init();

        $result = $controller->saveConfig();
        $this->assertArrayHasKey('config', $result);
    }

    public function testUserControllerCoversAdminLoginBranchesAndStaticManagers(): void
    {
        Security::setTest(true);

        $adminController = new class extends UserControllerProbe {
            public function isAdmin()
            {
                return true;
            }
        };
        $this->assertSame('', (string)$adminController->adminLogin());
    }

    public function testUserControllerStaticManagerFlowsWithInjectedSingletons(): void
    {
        Security::setTest(true);
        $this->injectSingleton(AdminServices::class, new AdminServicesCoverageStub());
        $this->injectSingleton(\PSFS\base\Template::class, new TemplateCoverageStub());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        Request::dropInstance();
        Request::getInstance()->init();
        $rendered = UserController::showAdminManager();
        $this->assertSame('rendered-admin', $rendered);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $adminForm = new AdminForm();
        $adminForm->build();
        $_REQUEST = [$adminForm->getName() => $this->buildRequestPayloadFromForm($adminForm)];
        Request::dropInstance();
        Request::getInstance()->init();
        $this->assertSame('rendered-admin', UserController::showAdminManager());

        $controller = $this->newUserProbe();
        $this->assertSame('rendered-admin', $controller->adminers());
        $this->assertSame('rendered-admin', $controller->setAdminUsers());

        $guestController = new class extends UserControllerProbe {
            public function isAdmin()
            {
                return false;
            }
        };
        $this->assertIsString((string)$guestController->adminLogin());
    }

    public function testUserControllerSwitchAdminLocaleUsesRefererOrRedirectFallback(): void
    {
        Security::setTest(true);
        Config::save(['i18n.locales' => 'en_US,es_ES'], []);
        Config::getInstance()->loadConfigData(true);

        $controller = $this->newUserProbe();
        Config::save(['default.language' => 'en_US'], []);
        Config::getInstance()->loadConfigData(true);
        $this->setObjectProperty($controller, 'config', Config::getInstance());

        $_SERVER['HTTP_REFERER'] = 'http://localhost:8080/admin/config';
        Request::dropInstance();
        Request::getInstance()->init();
        $this->assertSame('', $controller->switchAdminLocale('es-ES'));
        $this->assertNull($controller->lastRedirect);

        unset($_SERVER['HTTP_REFERER']);
        Request::dropInstance();
        Request::getInstance()->init();
        $this->assertSame('', $controller->switchAdminLocale('invalid-locale'));
        $this->assertSame('admin', $controller->lastRedirect);
    }

    public function testDocumentorControllerCoversJsonHtmlDownloadAndSwaggerUi(): void
    {
        $probe = $this->newDocumentorProbe();
        $router = Router::getInstance();
        $this->setObjectProperty($router, 'domains', [
            '@ROOT/' => ['base' => '/tmp/root/'],
        ]);

        $service = $this->getMockBuilder(DocumentorService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getModules', 'buildEndpointSpec', 'extractApiEndpoints', 'swaggerFormatter', 'postmanFormatter', 'openApiFormatter'])
            ->getMock();
        $service->method('getModules')->willReturnCallback(
            static fn (string $domain): array => $domain === 'EMPTY' ? [] : ['M']
        );
        $service->method('buildEndpointSpec')->willReturnCallback(
            static fn (array $module): array => ['endpoints' => $module]
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
        $service->method('openApiFormatter')->willReturnCallback(
            static fn (array $module, array $doc): array => ['openapi' => $doc]
        );
        $probe->setService($service);

        $probe->setRequestData(['type' => 'swagger']);
        $swagger = $probe->createApiDocs('ROOT');
        $this->assertSame(200, $swagger['status']);
        $this->assertArrayHasKey('swagger', $swagger['payload']);

        $probe->setRequestData(['type' => 'openapi']);
        $openapi = $probe->createApiDocs('ROOT');
        $this->assertSame(200, $openapi['status']);
        $this->assertArrayHasKey('openapi', $openapi['payload']);

        $probe->setRequestData(['type' => 'html']);
        $html = $probe->createApiDocs('ROOT');
        $this->assertSame('documentation.html.twig', $html['template']);

        $probe->setRequestData([]);
        $json = $probe->createApiDocs('ROOT');
        $this->assertSame(200, $json['status']);

        $probe->setRequestData(['type' => 'swagger', 'download' => 1]);
        $this->assertNull($probe->createApiDocs('ROOT'));
        $this->assertSame('swagger.json', $probe->lastDownload['filename'] ?? null);

        $probe->setRequestData(['type' => 'postman', 'download' => 1]);
        $this->assertNull($probe->createApiDocs('ROOT'));
        $this->assertSame('postman.collection.json', $probe->lastDownload['filename'] ?? null);

        $probe->setRequestData(['type' => 'openapi', 'download' => 1]);
        $this->assertNull($probe->createApiDocs('ROOT'));
        $this->assertSame('openapi.json', $probe->lastDownload['filename'] ?? null);

        $render = $probe->swaggerUi('ROOT');
        $this->assertSame('swagger.html.twig', $render['template']);

        $this->expectException(RouterException::class);
        $probe->swaggerUi('MISSING_DOMAIN');
    }

    public function testRouteControllerReturnsExpectedContracts(): void
    {
        $route = $this->newRouteProbe();
        $routing = $route->getRouting();
        $this->assertIsArray($routing);

        $routingPage = $route->printRoutes();
        $this->assertArrayHasKey('slugs', $routingPage);
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

    private function newConfigProbe(): ConfigControllerProbe
    {
        $reflection = new \ReflectionClass(ConfigControllerProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function newRouteProbe(): RouteControllerProbe
    {
        $reflection = new \ReflectionClass(RouteControllerProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildRequestPayloadFromForm(Form $form, array $overrides = []): array
    {
        $payload = [];
        foreach ($form->getFields() as $fieldName => $field) {
            if ($fieldName === Form::SEPARATOR) {
                continue;
            }
            if (array_key_exists($fieldName, $overrides)) {
                $payload[$fieldName] = $overrides[$fieldName];
                continue;
            }
            $value = $field['value'] ?? null;
            if ($value === null || $value === '') {
                $value = 'coverage';
            }
            $payload[$fieldName] = $value;
        }
        return $payload;
    }

    private function setObjectProperty(object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($target, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($target, $value);
    }

    private function injectSingleton(string $class, object $instance): void
    {
        $reflection = new \ReflectionProperty(SingletonRegistry::class, 'instances');
        $reflection->setAccessible(true);
        $instances = $reflection->getValue();
        $context = $_SERVER[SingletonRegistry::CONTEXT_SESSION] ?? SingletonRegistry::CONTEXT_SESSION;
        if (!isset($instances[$context]) || !is_array($instances[$context])) {
            $instances[$context] = [];
        }
        $instances[$context][$class] = $instance;
        $reflection->setValue(null, $instances);
    }
}

class UserControllerProbe extends UserController
{
    public ?string $lastRedirect = null;

    public function json($response, $statusCode = 200)
    {
        return $response;
    }

    public function redirect($route, array $params = [])
    {
        $this->lastRedirect = (string)$route;
        return '/redirect/' . $route;
    }
}

class AdminServicesCoverageStub extends AdminServices
{
    public function getAdmins()
    {
        return ['admin' => ['profile' => AuthHelper::ADMIN_ID_TOKEN]];
    }
}

class TemplateCoverageStub extends \PSFS\base\Template
{
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return 'rendered-admin';
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
    public ?array $lastDownload = null;

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

    public function download($response, $contentType = 'text/plain', $filename = 'file.txt'): void
    {
        $this->lastDownload = [
            'contentType' => $contentType,
            'filename' => $filename,
            'payload' => (string)$response,
        ];
    }

}

class ConfigControllerProbe extends ConfigController
{
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return $vars;
    }

    public function json($response, $statusCode = 200)
    {
        return $response;
    }
}

class RouteControllerProbe extends RouteController
{
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return $vars;
    }

    public function json($response, $statusCode = 200)
    {
        return $response;
    }

    public function redirect($route, array $params = [])
    {
        return '/route/' . $route;
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
