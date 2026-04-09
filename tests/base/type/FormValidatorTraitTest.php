<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\traits\Form\FormValidatorTrait;

class FormValidatorTraitTest extends TestCase
{
    public function testSetErrorAndGetErrorContracts(): void
    {
        $harness = new FormValidatorHarness();
        $harness->setError('name', 'invalid');

        $this->assertSame('invalid', $harness->getError('name'));
        $this->assertSame('invalid', $harness->fields['name']['error'] ?? null);
        $this->assertSame('', $harness->getError('missing'));
    }

    public function testCheckEmptyDetectsNullArraysAndWhitespace(): void
    {
        $harness = new FormValidatorHarness();

        $this->assertTrue($harness->checkEmptyPublic(null));
        $this->assertTrue($harness->checkEmptyPublic([]));
        $this->assertTrue($harness->checkEmptyPublic("  \n\r "));
        $this->assertFalse($harness->checkEmptyPublic('ok'));
    }

    public function testCheckFieldValidationFailsOnRequiredAndPatternAndPassesValidField(): void
    {
        $harness = new FormValidatorHarness();

        [$fieldRequired, $requiredValid] = $harness->checkFieldValidationPublic(
            ['value' => '', 'required' => true],
            'email'
        );
        $this->assertFalse($requiredValid);
        $this->assertArrayHasKey('error', $fieldRequired);

        [$fieldPattern, $patternValid] = $harness->checkFieldValidationPublic(
            ['value' => 'abc', 'pattern' => '^[0-9]+$', 'email' => []],
            'email'
        );
        $this->assertFalse($patternValid);
        $this->assertArrayHasKey('error', $fieldPattern);

        [$fieldOk, $ok] = $harness->checkFieldValidationPublic(
            ['value' => '123', 'pattern' => '^[0-9]+$', 'email' => []],
            'email'
        );
        $this->assertTrue($ok);
        $this->assertArrayNotHasKey('error', $fieldOk);
    }
}

class FormValidatorHarness
{
    use FormValidatorTrait;

    public array $fields = [
        'name' => [],
    ];

    public function checkEmptyPublic(mixed $value): bool
    {
        return $this->checkEmpty($value);
    }

    /**
     * @param array<string, mixed> $field
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public function checkFieldValidationPublic(array $field, string $key): array
    {
        $method = new \ReflectionMethod($this, 'checkFieldValidation');
        $method->setAccessible(true);
        return $method->invoke($this, $field, $key);
    }
}
