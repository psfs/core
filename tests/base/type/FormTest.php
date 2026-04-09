<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\exception\FormException;
use PSFS\base\types\Form;

class FormTest extends TestCase
{
    public function testGetBuildAndSaveHappyPath(): void
    {
        $model = new FormModelSaveDouble();
        $form = new FormTestHarness($model);
        $form->setMethod('GET');
        $form->add('username', ['value' => 'neo']);

        $this->assertSame($model, $form->get('model'));
        $this->assertNull($form->get('missing_property'));
        $this->assertSame($form, $form->build());
        $this->assertTrue($form->save());
        $this->assertSame([['username' => 'neo']], $model->fromArrayPayloads);
        $this->assertTrue($model->saved);
    }

    public function testSaveThrowsWhenNoModelWasProvided(): void
    {
        $form = new FormTestHarness();
        $form->setMethod('GET');

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('No model has been associated with the form');
        $form->save();
    }

    public function testSaveWrapsUnderlyingModelException(): void
    {
        $model = new FormModelSaveDouble();
        $model->throwOnSave = true;
        $form = new FormTestHarness($model);
        $form->setMethod('GET');
        $form->add('username', ['value' => 'neo']);

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('boom');
        $form->save();
    }

    public function testBuildOnPostAddsCsrfFields(): void
    {
        $form = new FormTestHarness(new FormModelSaveDouble());
        $form->setMethod('POST');
        $form->add('username', ['value' => 'neo']);

        $form->build();
        $fields = $form->getFields();

        $this->assertArrayHasKey('form_test_harness_token', $fields);
        $this->assertArrayHasKey('form_test_harness_token_key', $fields);
    }
}

class FormTestHarness extends Form
{
    public function getName(): string
    {
        return 'form_test_harness';
    }
}

class FormModelSaveDouble
{
    public array $fromArrayPayloads = [];
    public bool $saved = false;
    public bool $throwOnSave = false;

    public function fromArray(array $payload): void
    {
        $this->fromArrayPayloads[] = $payload[0] ?? [];
    }

    public function save(): void
    {
        if ($this->throwOnSave) {
            throw new \RuntimeException('boom');
        }
        $this->saved = true;
    }

    public function getPrimaryKey(): int
    {
        return 123;
    }
}
