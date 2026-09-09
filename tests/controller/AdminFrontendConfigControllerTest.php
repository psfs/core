<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\controller\AdminFrontendConfigController;

class AdminFrontendConfigControllerTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        Security::setTest(true);
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        Security::setTest(false);
        Security::dropInstance();
    }

    public function testConfigReadDoesNotExposeConfiguredSecrets(): void
    {
        $body = (new AdminFrontendConfigControllerProbe())->show();

        self::assertStringContainsString('"ok":true', $body);
        self::assertStringContainsString('"app.name"', $body);
        self::assertStringNotContainsString('do-not-leak', $body);
        self::assertStringContainsString('"value":""', $body);
    }

    public function testConfigReadPublishesTheLegacyParameterSuggestionsForNewEntries(): void
    {
        $response = json_decode((new AdminFrontendConfigControllerProbe())->show(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('suggestions', $response['data']);
        self::assertContains('db.host', $response['data']['suggestions']);
        self::assertContains('admin.front.path', $response['data']['suggestions']);
    }

    public function testBlankMaskedSecretIsPersistedFromTheExistingConfiguration(): void
    {
        $controller = new AdminFrontendConfigControllerProbe([
            'values' => ['app.name' => 'PSFS v2', 'root.api.secret' => ''],
            'extra' => [],
        ]);

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertSame('do-not-leak', $controller->savedValues['root.api.secret']);
    }

    public function testBlankNewMaskedSecretIsRemovedInsteadOfPersisted(): void
    {
        $controller = new AdminFrontendConfigControllerProbe([
            'values' => ['app.name' => 'PSFS v2', 'missing.api.token' => ''],
            'extra' => [],
        ]);

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertArrayNotHasKey('missing.api.token', $controller->savedValues);
    }

    public function testNewExtraEntriesAreAdaptedToTheLegacyPersistenceContract(): void
    {
        $controller = new AdminFrontendConfigControllerProbe([
            'values' => ['app.name' => 'PSFS v2'],
            'extra' => ['custom.flag' => 'enabled'],
        ]);

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertSame(['label' => ['custom.flag'], 'value' => ['enabled']], $controller->savedExtra);
    }

    public function testExtraNormalizationSkipsBlankLabelsAndCompositeValues(): void
    {
        $controller = new AdminFrontendConfigControllerProbe([
            'values' => ['app.name' => 'PSFS v2'],
            'extra' => [
                ' ' => 'ignored',
                'nested' => ['not-persistable'],
                'feature.enabled' => false,
                'retry.limit' => 3,
            ],
        ]);

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertSame(
            ['label' => ['feature.enabled', 'retry.limit'], 'value' => [false, 3]],
            $controller->savedExtra
        );
    }

    public function testInvalidConfigWriteReturnsFieldErrorsWithoutSaving(): void
    {
        $controller = new AdminFrontendConfigControllerProbe([
            'values' => ['app.name' => ''],
            'extra' => [],
        ]);

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode, json_encode($response));
        self::assertFalse($response['ok']);
        self::assertArrayHasKey('app.name', $response['errors'], json_encode($response));
        self::assertNotEmpty($response['errors']['app.name']);
        self::assertFalse($controller->saved);
    }

    public function testEmptyValuesPayloadIsRejectedBeforeAnySave(): void
    {
        $controller = new AdminFrontendConfigControllerProbe(['values' => [], 'extra' => []]);
        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $controller->statusCode);
        self::assertSame(['payload' => ['Expected values and extra objects']], $response['errors']);
        self::assertFalse($controller->saved);
    }

    public function testConfigFormRejectsAnEmptyRequiredValueOutsideTheLegacyCsrfFlow(): void
    {
        $form = new ConfigForm('/admin/api/v2/config', ['app.name'], [], ['app.name' => 'PSFS']);
        $form->setMethod('PUT')->build();
        $form->setData(['app.name' => '']);

        self::assertFalse($form->isValid());
    }

    public function testConcreteControllerSeamsExposeTheRuntimeConfigurationContract(): void
    {
        $controller = new AdminFrontendConfigController();

        $formMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'configForm');
        $payloadMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'requestPayload');
        $debugMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'debugMode');
        $runtimeDebugMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'runtimeDebugMode');
        $authorizationMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'assertSuperAdminConfigWriteAccess');

        self::assertInstanceOf(ConfigForm::class, $formMethod->invoke($controller));
        self::assertIsArray($payloadMethod->invoke($controller));
        self::assertIsBool($debugMethod->invoke($controller));
        self::assertIsBool($runtimeDebugMethod->invoke($controller));
        $authorizationMethod->invoke($controller);
        self::addToAssertionCount(1);
    }

    public function testSuccessfulSaveAppliesLegacyPostSaveEffectsForEveryDebugTransition(): void
    {
        foreach ([
            [true, false, 1, 1],
            [true, true, 0, 0],
            [false, true, 0, 1],
            [false, false, 1, 0],
        ] as [$debugBefore, $debugAfter, $expectedCacheRefreshes, $expectedDocumentRootClears]) {
            $controller = new AdminFrontendConfigControllerProbe(
                ['values' => ['app.name' => 'PSFS v2'], 'extra' => []],
                $debugBefore,
                $debugAfter
            );

            $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

            self::assertTrue($response['ok']);
            self::assertSame($expectedCacheRefreshes, $controller->cacheRefreshes);
            self::assertSame($expectedDocumentRootClears, $controller->documentRootClears);
        }
    }

    public function testFailedSaveDoesNotRunPostSaveEffects(): void
    {
        $controller = new AdminFrontendConfigControllerProbe(
            ['values' => ['app.name' => 'PSFS v2'], 'extra' => []],
            true,
            false,
            false
        );

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($response['ok']);
        self::assertSame(500, $controller->statusCode);
        self::assertSame(0, $controller->cacheRefreshes);
        self::assertSame(0, $controller->documentRootClears);
    }

    public function testConcreteConfigurationSeamsPersistSecretsAndBuildSuggestions(): void
    {
        $config = Config::getInstance()->dumpConfig();
        $config['root.api.secret'] = 'existing-secret';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $controller = new AdminFrontendConfigController();
        $saveMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'save');
        self::assertTrue($saveMethod->invoke($controller, ['app.name' => 'PSFS'], []));

        $configInstance = Config::getInstance();
        $configProperty = new \ReflectionProperty(Config::class, 'config');
        $configProperty->setValue($configInstance, ['root.api.secret' => 'existing-secret']);
        $form = new ConfigForm('/admin/api/v2/config', ['root.api.secret'], [], ['root.api.secret' => 'existing-secret']);
        $retainMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'retainExistingMaskedSecrets');
        $retained = $retainMethod->invoke($controller, $form, ['root.api.secret' => '']);
        self::assertSame('existing-secret', $retained['root.api.secret']);

        $router = Router::getInstance();
        $domains = new \ReflectionProperty(Router::class, 'domains');
        $originalDomains = $domains->getValue($router);
        try {
            $domains->setValue($router, ['@CLIENT/' => []]);
            $suggestionsMethod = new \ReflectionMethod(AdminFrontendConfigController::class, 'suggestions');
            self::assertContains('client.api.secret', $suggestionsMethod->invoke($controller));
        } finally {
            $domains->setValue($router, $originalDomains);
        }
    }

    public function testConfigurationFieldErrorsExposeFormValidationMessages(): void
    {
        $controller = new AdminFrontendConfigController();
        $form = new ConfigForm('/admin/api/v2/config', ['app.name'], [], ['app.name' => 'PSFS']);
        $form->setMethod('PUT')->build();
        $form->setData(['app.name' => '']);
        $form->isValid();

        $method = new \ReflectionMethod(AdminFrontendConfigController::class, 'fieldErrors');
        $errors = $method->invoke($controller, $form);

        self::assertArrayHasKey('app.name', $errors);
    }

    public function testSuccessfulSaveDelegatesPostSaveBehaviorThroughTheProtectedSeam(): void
    {
        $controller = new AdminFrontendConfigControllerDelegationProbe(
            ['values' => ['app.name' => 'PSFS v2'], 'extra' => []]
        );

        $response = json_decode($controller->update(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($response['ok']);
        self::assertSame(1, $controller->postSaveEffectsCalls);
    }
}

class AdminFrontendConfigControllerProbe extends AdminFrontendConfigController
{
    public int $statusCode = 200;
    public bool $saved = false;
    public int $cacheRefreshes = 0;
    public int $documentRootClears = 0;
    /** @var array<string,mixed> */
    public array $savedValues = [];
    /** @var array<string,mixed> */
    public array $savedExtra = [];

    /** @param array<string,mixed> $payload */
    public function __construct(
        private readonly array $payload = [],
        private readonly bool $debugBefore = true,
        private readonly bool $debugAfter = true,
        private readonly bool $saveSucceeds = true
    )
    {
    }

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    protected function configForm(): ConfigForm
    {
        return new ConfigForm('/admin/api/v2/config', ['app.name'], ['root.api.secret', 'missing.api.token'], [
            'app.name' => 'PSFS',
            'root.api.secret' => 'do-not-leak',
        ]);
    }

    /** @return array<string,mixed> */
    protected function requestPayload(): array
    {
        return $this->payload;
    }

    protected function save(array $values, array $extra): bool
    {
        $this->saved = true;
        $this->savedValues = $values;
        $this->savedExtra = $extra;
        return $this->saveSucceeds;
    }

    protected function debugMode(): bool
    {
        return $this->debugBefore;
    }

    protected function runtimeDebugMode(): bool
    {
        return $this->debugAfter;
    }

    protected function refreshCacheState(): void
    {
        ++$this->cacheRefreshes;
    }

    protected function clearDocumentRoot(): void
    {
        ++$this->documentRootClears;
    }
}

class AdminFrontendConfigControllerDelegationProbe extends AdminFrontendConfigControllerProbe
{
    public int $postSaveEffectsCalls = 0;

    protected function applyPostSaveEffects(bool $previousDebug): void
    {
        ++$this->postSaveEffectsCalls;
    }
}
