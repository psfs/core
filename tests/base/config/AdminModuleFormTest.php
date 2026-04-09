<?php

namespace PSFS\tests\base\config;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\AdminForm;
use PSFS\base\config\ModuleForm;

class AdminModuleFormTest extends TestCase
{
    public function testAdminFormBuildsExpectedSchema(): void
    {
        $form = new AdminForm();
        $fields = $form->getFields();
        $buttons = $form->getButtons();

        $this->assertSame('/admin/setup', $form->getAction());
        $this->assertArrayHasKey('username', $fields);
        $this->assertArrayHasKey('password', $fields);
        $this->assertArrayHasKey('profile', $fields);
        $this->assertSame('password', $fields['password']['type'] ?? null);
        $this->assertSame('select', $fields['profile']['type'] ?? null);
        $this->assertSame('admin_setup', $form->getName());
        $this->assertSame('Admin user control panel', $form->getTitle());
        $this->assertArrayHasKey('submit', $buttons);
    }

    public function testModuleFormBuildsExpectedSchema(): void
    {
        $form = new ModuleForm();
        $fields = $form->getFields();
        $buttons = $form->getButtons();

        $this->assertSame('/admin/module', $form->getAction());
        $this->assertArrayHasKey('module', $fields);
        $this->assertArrayHasKey('controllerType', $fields);
        $this->assertArrayHasKey('api', $fields);
        $this->assertSame('select', $fields['controllerType']['type'] ?? null);
        $this->assertFalse($fields['controllerType']['required'] ?? true);
        $this->assertFalse($fields['api']['required'] ?? true);
        $this->assertSame('admin_modules', $form->getName());
        $this->assertSame('Module Management', $form->getTitle());
        $this->assertSame('Generate module', $buttons['submit']['value'] ?? null);
    }
}
