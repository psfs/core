<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\config\ModuleForm;
use PSFS\controller\AdminFrontendModulesController;

class AdminFrontendModulesControllerTest extends TestCase
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

    public function testModuleSchemaIsJsonAndContainsControllerTypeOptions(): void
    {
        $body = (new AdminFrontendModulesControllerProbe())->schema();
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertArrayHasKey('controllerType', $response['data']['form']['fields']);
        self::assertSame('select', $response['data']['form']['fields']['controllerType']['type']);
        self::assertArrayHasKey('AuthAdmin', $response['data']['form']['fields']['controllerType']['options']);
        self::assertStringNotContainsString('<form', $body);
    }

    public function testInvalidModuleNameReturns422BeforeGeneratorIsCalled(): void
    {
        $controller = new AdminFrontendModulesControllerProbe(['values' => ['module' => '']]);
        $response = json_decode($controller->create(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode, json_encode($response));
        self::assertFalse($response['ok']);
        self::assertArrayHasKey('module', $response['errors']);
        self::assertFalse($controller->generated);
    }

    public function testEmptyModulePayloadReturns422BeforeGenerating(): void
    {
        $controller = new AdminFrontendModulesControllerProbe(['values' => []]);
        $response = json_decode($controller->create(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertArrayHasKey('payload', $response['errors']);
        self::assertFalse($controller->generated);
    }

    public function testUnknownCustomApiReturns422WithoutGeneratingTheModule(): void
    {
        $controller = new AdminFrontendModulesControllerProbe(['values' => [
            'module' => 'foo/bar',
            'controllerType' => 'Normal',
            'api' => 'Missing\\CustomApi',
        ]]);

        $response = json_decode($controller->create(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertFalse($response['ok']);
        self::assertStringContainsString('does not exist', $response['message']);
        self::assertFalse($controller->generated);
    }

    public function testValidModuleNormalizesLegacySeparatorsBeforeGenerating(): void
    {
        foreach (['foo\\bar', '/foo/bar'] as $input) {
            $controller = new AdminFrontendModulesControllerProbe(['values' => [
                'module' => $input,
                'controllerType' => 'Normal',
                'api' => '',
            ]]);

            $response = json_decode($controller->create(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(200, $controller->statusCode, json_encode($response));
            self::assertTrue($response['ok']);
            self::assertTrue($controller->generated);
            self::assertSame(['FOO/BAR', '', ''], $controller->generatedArguments);
            self::assertSame('FOO/BAR', $response['data']['module']);
        }
    }

    public function testValidationHelpersReportRequiredAndFieldLevelErrors(): void
    {
        $controller = new AdminFrontendModulesControllerProbe();
        $form = new AdminFrontendModuleFormProbe([
            'module' => ['required' => true, 'value' => ''],
            'controllerType' => ['required' => false],
            'api' => ['error' => 'invalid api'],
        ]);

        $required = $controller->callRequiredFieldErrors($form);
        $fieldErrors = $controller->callFieldErrors($form);

        self::assertArrayHasKey('module', $required);
        self::assertSame(['invalid api'], $fieldErrors['api']);
    }

    public function testConcreteModuleControllerKeepsItsDefaultFormAndRequestSeams(): void
    {
        $controller = new AdminFrontendModulesController();
        $formMethod = new \ReflectionMethod(AdminFrontendModulesController::class, 'moduleForm');
        $payloadMethod = new \ReflectionMethod(AdminFrontendModulesController::class, 'requestPayload');

        self::assertInstanceOf(ModuleForm::class, $formMethod->invoke($controller));
        self::assertIsArray($payloadMethod->invoke($controller));
    }
}

class AdminFrontendModulesControllerProbe extends AdminFrontendModulesController
{
    public int $statusCode = 200;
    public bool $generated = false;

    /** @var array{string,string,string}|null */
    public ?array $generatedArguments = null;

    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload = [])
    {
    }

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    protected function moduleForm(): ModuleForm
    {
        return new ModuleForm();
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return $this->payload;
    }

    protected function generateModule(string $module, string $type, string $apiClass): void
    {
        $this->generated = true;
        $this->generatedArguments = [$module, $type, $apiClass];
    }

    /** @return array<string,string[]> */
    public function callRequiredFieldErrors(ModuleForm $form): array
    {
        $method = new \ReflectionMethod(AdminFrontendModulesController::class, 'requiredFieldErrors');
        return $method->invoke($this, $form);
    }

    /** @return array<string,string[]> */
    public function callFieldErrors(ModuleForm $form): array
    {
        $method = new \ReflectionMethod(AdminFrontendModulesController::class, 'fieldErrors');
        return $method->invoke($this, $form);
    }
}

class AdminFrontendModuleFormProbe extends ModuleForm
{
    public function __construct(private readonly array $probeFields)
    {
    }

    public function getFields(): array
    {
        return $this->probeFields;
    }
}
