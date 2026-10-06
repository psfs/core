<?php

namespace PSFS\tests\base\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Cache;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\controller\ConfigController;

class ConfigControllerTest extends TestCase
{
    private string $adminsPath;
    private bool $adminsExisted = false;
    private string $adminsBackup = '';

    protected function setUp(): void
    {
        $this->adminsPath = CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json';
        Security::setTest(false);
        Security::dropInstance();
        if (file_exists($this->adminsPath)) {
            $this->adminsExisted = true;
            $this->adminsBackup = (string)file_get_contents($this->adminsPath);
        }
    }

    protected function tearDown(): void
    {
        Security::dropInstance();
        if ($this->adminsExisted) {
            file_put_contents($this->adminsPath, $this->adminsBackup);
        } else {
            @unlink($this->adminsPath);
        }
    }

    public function testGetConfigParamsMergesBaseParamsAndDomainSecrets(): void
    {
        $controller = $this->newController();

        $response = $controller->getConfigParams();

        $this->assertIsArray($response);
        foreach (Config::$required as $requiredParam) {
            $this->assertContains($requiredParam, $response);
        }
        foreach (Config::$optional as $optionalParam) {
            $this->assertContains($optionalParam, $response);
        }
        $domainSecrets = array_values(array_filter(
            $response,
            static fn ($param) => is_string($param) && str_ends_with($param, '.api.secret')
        ));
        $this->assertNotEmpty($domainSecrets);
    }

    public function testSaveConfigRejectsManagerWithoutSuperAdminRole(): void
    {
        $this->seedAdmins([
            'manager' => ['hash' => sha1('manager:pass'), 'profile' => AuthHelper::MANAGER_ID_TOKEN],
        ]);
        Security::getInstance()->updateAdmin('manager', AuthHelper::MANAGER_ID_TOKEN);
        $controller = $this->newController();

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Restricted area');

        $controller->saveConfig();
    }

    public function testSaveConfigClearsSubmittedCookieSecretBeforeRendering(): void
    {
        $config = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $originalConfig = $configProperty->getValue($config);
        $existingSecret = bin2hex(random_bytes(32));
        $submittedSecret = 'submitted-cookie-secret-sentinel';
        $configProperty->setValue($config, array_merge($originalConfig, ['auth.cookie.secret' => $existingSecret]));

        $originalRequest = $_REQUEST;
        $originalGet = $_GET;
        $_REQUEST = ['config' => ['auth.cookie.secret' => $submittedSecret]];
        $_GET = [];
        Request::dropInstance();
        Request::getInstance()->init();
        Security::setTest(true);

        try {
            $rendered = $this->newController()->saveConfig();
            $fields = $rendered['config']->getFields();

            $this->assertSame('', $fields['auth.cookie.secret']['value']);
            $this->assertStringNotContainsString($submittedSecret, json_encode($fields));
        } finally {
            Security::setTest(false);
            Request::dropInstance();
            $_REQUEST = $originalRequest;
            $_GET = $originalGet;
            $configProperty->setValue($config, $originalConfig);
        }
    }

    private function seedAdmins(array $admins): void
    {
        Cache::getInstance()->storeData($this->adminsPath, $admins, Cache::JSONGZ, true);
    }

    private function newController(): ConfigControllerJsonProbe
    {
        $reflection = new \ReflectionClass(ConfigControllerJsonProbe::class);
        return $reflection->newInstanceWithoutConstructor();
    }
}

class ConfigControllerJsonProbe extends ConfigController
{
    public function json($response, $statusCode = 200)
    {
        return $response;
    }

    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        return $vars;
    }
}
