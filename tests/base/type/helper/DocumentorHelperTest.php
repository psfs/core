<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\DocumentorHelper;

class DocumentorHelperTest extends TestCase
{
    public function testTranslateSwaggerFormatsCoversKnownAliases(): void
    {
        $this->assertSame(['boolean', ''], DocumentorHelper::translateSwaggerFormats('bool'));
        $this->assertSame(['integer', 'int32'], DocumentorHelper::translateSwaggerFormats('int'));
        $this->assertSame(['number', 'float'], DocumentorHelper::translateSwaggerFormats('float'));
        $this->assertSame(['string', 'password'], DocumentorHelper::translateSwaggerFormats('varbinary'));
        $this->assertSame(['string', 'date-time'], DocumentorHelper::translateSwaggerFormats('datetime'));
    }

    public function testTranslateSwaggerFormatsDefaultsToString(): void
    {
        $this->assertSame(['string', ''], DocumentorHelper::translateSwaggerFormats('unknown_type'));
    }
}
