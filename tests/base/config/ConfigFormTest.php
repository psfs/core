<?php

namespace PSFS\tests\base\config;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;

class ConfigFormTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testBuildsRequiredOptionalAndExtraFields(): void
    {
        $required = ['db.password'];
        $optional = ['custom.password', 'custom.empty'];
        $data = [
            'db.password' => 'main_secret',
            'custom.password' => 'opt_secret',
            'custom.empty' => '',
            'extra.value' => 'extra_data',
        ];

        $form = new ConfigForm('/admin/config', $required, $optional, $data);
        $fields = $form->getFields();

        $this->assertArrayHasKey('db.password', $fields);
        $this->assertSame('password', $fields['db.password']['type']);
        $this->assertArrayHasKey('custom.password', $fields);
        $this->assertSame('password', $fields['custom.password']['type']);
        $this->assertArrayNotHasKey('custom.empty', $fields);
        $this->assertArrayHasKey('extra.value', $fields);
        $this->assertSame('text', $fields['extra.value']['type']);
        $this->assertSame('form-horizontal', $form->getAttrs()['class']);
    }

    public function testAddFieldButtonUsesLegacyOnclickContract(): void
    {
        $form = new ConfigForm('/admin/config', ['db.password'], [], []);
        $button = $form->getButtons()['add_field'] ?? [];

        $this->assertArrayHasKey('onclick', $button);
        $this->assertStringContainsString('addNewField', $button['onclick']);
        $this->assertArrayNotHasKey('ng-click', $button);
    }
}
