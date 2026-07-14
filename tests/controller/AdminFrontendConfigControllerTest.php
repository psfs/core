<?php

namespace PSFS\tests\controller;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\config\ConfigForm;
use PSFS\controller\AdminFrontendConfigController;

class AdminFrontendConfigControllerTest extends TestCase
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
}

class AdminFrontendConfigControllerProbe extends AdminFrontendConfigController
{
    public int $statusCode = 200;
    public bool $saved = false;
    /** @var array<string,mixed> */
    public array $savedValues = [];
    /** @var array<string,mixed> */
    public array $savedExtra = [];

    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload = [])
    {
    }

    public function json($response, $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        return (string) json_encode($response, JSON_UNESCAPED_SLASHES);
    }

    protected function configForm(): ConfigForm
    {
        return new ConfigForm('/admin/api/v2/config', ['app.name'], ['root.api.secret'], [
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
        return true;
    }
}
