<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\UserAuthException;
use PSFS\base\types\AuthController;
use PSFS\base\types\CustomAuthApi;

class AuthControllerGuardProbe extends AuthController
{
    public bool $logged = false;
    public ?string $templatePath = null;

    protected function setTemplatePath($path)
    {
        $this->templatePath = (string)$path;
        return $this;
    }

    public function isLogged()
    {
        return $this->logged;
    }
}

class CustomAuthApiGuardProbe extends CustomAuthApi
{
    public bool $logged = false;

    protected function hydrateRequestData()
    {
    }

    protected function hydrateOrders()
    {
    }

    protected function checkFieldType()
    {
    }

    public function getDomain()
    {
        return 'psfs';
    }

    public function isLogged()
    {
        return $this->logged;
    }
}

class AuthGuardsContractTest extends TestCase
{
    public function testAuthControllerThrowsWhenUserIsNotLogged(): void
    {
        $probe = $this->newInstanceWithoutConstructor(AuthControllerGuardProbe::class);
        $probe->setLoaded(true);
        $probe->logged = false;

        $this->expectException(UserAuthException::class);
        $probe->init();
    }

    public function testAuthControllerAllowsLoggedUsers(): void
    {
        $probe = $this->newInstanceWithoutConstructor(AuthControllerGuardProbe::class);
        $probe->setLoaded(true);
        $probe->logged = true;

        $probe->init();
        $this->assertNotNull($probe->templatePath);
    }

    public function testCustomAuthApiThrowsUnauthorizedWhenUserIsNotLogged(): void
    {
        $probe = $this->newInstanceWithoutConstructor(CustomAuthApiGuardProbe::class);
        $probe->setLoaded(true);
        $probe->logged = false;

        try {
            $probe->init();
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $this->assertSame(401, $e->getCode());
        }
    }

    public function testCustomAuthApiAllowsLoggedUsers(): void
    {
        $probe = $this->newInstanceWithoutConstructor(CustomAuthApiGuardProbe::class);
        $probe->setLoaded(true);
        $probe->logged = true;

        $probe->init();
        $this->assertTrue(true);
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    private function newInstanceWithoutConstructor(string $class): object
    {
        $reflection = new \ReflectionClass($class);
        return $reflection->newInstanceWithoutConstructor();
    }
}
