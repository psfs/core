<?php

namespace PSFS\tests\base\admin;

use PHPUnit\Framework\TestCase;
use PSFS\base\admin\AdminApiResponse;
use PSFS\base\admin\AdminFormSchemaFactory;
use PSFS\base\config\AdminForm;
use PSFS\base\config\ConfigForm;

class AdminFormSchemaFactoryTest extends TestCase
{
    public function testRemovesSecurityTokensAndMasksSecretValues(): void
    {
        $form = (new ConfigForm('/admin/config', [], ['root.api.secret'], [
            'root.api.secret' => 'must-not-leak',
        ]))->build();

        $schema = (new AdminFormSchemaFactory())->fromForm($form);

        self::assertArrayNotHasKey('__token', $schema['fields']);
        self::assertArrayNotHasKey('__token_key', $schema['fields']);
        self::assertSame('', $schema['fields']['root.api.secret']['value']);
        self::assertNotContains('must-not-leak', $schema, true);
    }

    public function testKeepsFieldTypeOptionsAndRequiredFlag(): void
    {
        $schema = (new AdminFormSchemaFactory())->fromForm(new AdminForm());

        self::assertSame('select', $schema['fields']['profile']['type']);
        self::assertTrue($schema['fields']['profile']['required']);
        self::assertNotEmpty($schema['fields']['profile']['options']);
        self::assertSame('admin_setup', $schema['name']);
        self::assertSame('Admin user control panel', $schema['title']);
    }

    public function testBuildsSuccessAndFailureJsonEnvelopes(): void
    {
        self::assertSame(
            '{"ok":true,"message":"Saved","data":{"name":"admin_setup"},"errors":{}}',
            json_encode(AdminApiResponse::success(['name' => 'admin_setup'], 'Saved'), JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            '{"ok":true,"message":null,"data":{"name":"admin_setup"},"errors":{}}',
            json_encode(AdminApiResponse::success(['name' => 'admin_setup']), JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            '{"ok":false,"message":"Invalid form","data":null,"errors":{"profile":["Required"]}}',
            json_encode(AdminApiResponse::failure('Invalid form', ['profile' => 'Required']), JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            '{"ok":false,"message":"Invalid form","data":null,"errors":{"profile":["Required","Must be an email"]}}',
            json_encode(AdminApiResponse::failure('Invalid form', ['profile' => ['Required', 'Must be an email']]), JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            '{"ok":false,"message":"Invalid form","data":null,"errors":{}}',
            json_encode(AdminApiResponse::failure('Invalid form'), JSON_THROW_ON_ERROR)
        );
    }
}
