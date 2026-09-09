<?php

namespace PSFS\tests\base\dto;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\dto\ValidationContext;

class ValidationContextTest extends TestCase
{
    public function testFromRequestUsesRawPayloadAndExplicitValidationFlags(): void
    {
        $request = Request::getInstance();
        $raw = new \ReflectionProperty(Request::class, 'raw');
        $data = new \ReflectionProperty(Request::class, 'data');
        $raw->setValue($request, ['json' => 'payload']);
        $data->setValue($request, ['legacy' => 'form']);

        $context = ValidationContext::fromRequest(false, false);

        self::assertSame(['json' => 'payload'], $context->payload);
        self::assertFalse($context->strictUnknownFields);
        self::assertFalse($context->enforceCsrf);
    }

    public function testFromRequestFallsBackToLegacyFormDataWhenRawPayloadIsEmpty(): void
    {
        $request = Request::getInstance();
        $raw = new \ReflectionProperty(Request::class, 'raw');
        $data = new \ReflectionProperty(Request::class, 'data');
        $raw->setValue($request, []);
        $data->setValue($request, ['legacy' => 'form']);

        $context = ValidationContext::fromRequest();

        self::assertSame(['legacy' => 'form'], $context->payload);
        self::assertTrue($context->strictUnknownFields);
        self::assertNull($context->enforceCsrf);
    }

    public function testHeaderLookupSupportsExactCaseInsensitiveAndUnderscoreNames(): void
    {
        $context = new ValidationContext([], [
            'X-Exact' => 'exact',
            'X-Correlation' => 'correlation',
            'X_CORRELATION_ID' => 42,
            'X-Multi' => ['not-a-header'],
        ]);

        self::assertSame('exact', $context->header('X-Exact'));
        self::assertSame('correlation', $context->header('x-correlation'));
        self::assertSame('42', $context->header('x-correlation-id'));
        self::assertNull($context->header('X-Multi'));
        self::assertNull($context->header('missing-header'));
    }
}
