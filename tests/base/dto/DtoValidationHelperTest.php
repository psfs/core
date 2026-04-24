<?php

namespace PSFS\tests\base\dto;

use PHPUnit\Framework\TestCase;
use PSFS\base\dto\DtoValidationHelper;
use PSFS\base\dto\ValidationContext;
use PSFS\base\types\helpers\attributes\CsrfField;
use PSFS\base\types\helpers\attributes\CsrfProtected;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Nullable;
use ReflectionClass;

class DtoValidationHelperTest extends TestCase
{
    public function testDefaultValueForReadsAttributeBeforeDocBlock(): void
    {
        $reflector = new ReflectionClass(HelperMetadataDto::class);

        $this->assertSame(
            'attr_default',
            DtoValidationHelper::defaultValueFor($reflector->getProperty('withAttribute'))
        );
        $this->assertSame(
            'doc_default',
            DtoValidationHelper::defaultValueFor($reflector->getProperty('withDocBlock'))
        );
        $this->assertNull(DtoValidationHelper::defaultValueFor($reflector->getProperty('plain')));
    }

    public function testNullableAndDeclaredTypeChecksAreDirectlyTestable(): void
    {
        $reflector = new ReflectionClass(HelperMetadataDto::class);

        $this->assertTrue(DtoValidationHelper::allowsNull($reflector->getProperty('nullable')));
        $this->assertFalse(DtoValidationHelper::allowsNull($reflector->getProperty('plain')));

        $this->assertTrue(DtoValidationHelper::matchesDeclaredType(3, 'int|string'));
        $this->assertTrue(DtoValidationHelper::matchesDeclaredType('3', 'int|string'));
        $this->assertFalse(DtoValidationHelper::matchesDeclaredType([], 'int|string'));
        $this->assertTrue(DtoValidationHelper::matchesDeclaredType(3, 'number'));
        $this->assertTrue(DtoValidationHelper::matchesDeclaredType(3.5, 'number'));
    }

    public function testAllowedPayloadFieldsIncludesCsrfOnlyWhenEnabled(): void
    {
        $reflector = new ReflectionClass(HelperCsrfPayloadDto::class);
        $properties = $reflector->getProperties();

        $allowed = DtoValidationHelper::allowedPayloadFields($properties, $reflector, null);
        $this->assertArrayHasKey('name', $allowed);
        $this->assertArrayHasKey('token', $allowed);
        $this->assertArrayHasKey('token_key', $allowed);

        $withoutCsrf = DtoValidationHelper::allowedPayloadFields($properties, $reflector, false);
        $this->assertArrayHasKey('name', $withoutCsrf);
        $this->assertArrayNotHasKey('token', $withoutCsrf);
        $this->assertArrayNotHasKey('token_key', $withoutCsrf);
    }

    public function testCsrfTokensPreferPayloadAndFallbackToHeaders(): void
    {
        $reflector = new ReflectionClass(HelperCsrfPayloadDto::class);
        $csrfProtected = DtoValidationHelper::csrfProtection($reflector);
        $csrfField = DtoValidationHelper::csrfField($reflector);

        $this->assertInstanceOf(CsrfProtected::class, $csrfProtected);
        $this->assertInstanceOf(CsrfField::class, $csrfField);
        $this->assertSame('helper_form', DtoValidationHelper::csrfFormKey($csrfProtected, $reflector));

        $payloadContext = new ValidationContext(['token' => 'payload_token', 'token_key' => 'payload_key']);
        $this->assertSame(
            ['payload_token', 'payload_key'],
            DtoValidationHelper::csrfTokensFromRequest($payloadContext, $csrfProtected, $csrfField)
        );

        $headerContext = new ValidationContext([], [
            'X-CSRF-Token' => 'header_token',
            'X-CSRF-Key' => 'header_key',
        ]);
        $this->assertSame(
            ['header_token', 'header_key'],
            DtoValidationHelper::csrfTokensFromRequest($headerContext, $csrfProtected, $csrfField)
        );
    }

    public function testPayloadScalarRejectsMissingAndNonScalarValues(): void
    {
        $payload = [
            'string' => 'value',
            'integer' => 7,
            'array' => ['nope'],
        ];

        $this->assertSame('value', DtoValidationHelper::payloadScalar($payload, 'string'));
        $this->assertSame('7', DtoValidationHelper::payloadScalar($payload, 'integer'));
        $this->assertSame('', DtoValidationHelper::payloadScalar($payload, 'array'));
        $this->assertSame('', DtoValidationHelper::payloadScalar($payload, 'missing'));
    }
}

class HelperMetadataDto
{
    #[DefaultValue('attr_default')]
    public ?string $withAttribute = null;

    /**
     * @default doc_default
     */
    public ?string $withDocBlock = null;

    #[Nullable]
    public ?string $nullable = null;

    public ?string $plain = null;
}

#[CsrfProtected(formKey: 'helper_form')]
#[CsrfField('token', 'token_key')]
class HelperCsrfPayloadDto
{
    public ?string $name = null;
}
