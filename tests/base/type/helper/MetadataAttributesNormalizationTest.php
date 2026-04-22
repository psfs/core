<?php

namespace PSFS\tests\base\type\helper;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\attributes\Action;
use PSFS\base\types\helpers\attributes\ApiDeprecated;
use PSFS\base\types\helpers\attributes\Api;
use PSFS\base\types\helpers\attributes\ApiReturn;
use PSFS\base\types\helpers\attributes\Cacheable;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Header;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Required;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Payload;
use PSFS\base\types\helpers\attributes\Values;
use PSFS\base\types\helpers\attributes\VarType;
use PSFS\base\types\helpers\attributes\Visible;

class MetadataAttributesNormalizationTest extends TestCase
{
    public function testStringBasedAttributesAreTrimmed(): void
    {
        $this->assertSame('admin.action', (new Action('  admin.action  '))->resolve());
        $this->assertSame('MY_API', (new Api('  MY_API  '))->resolve());
        $this->assertSame('X-API-LANG', (new Header('  X-API-LANG  '))->resolve());
        $this->assertSame('fa-cogs', (new Icon('  fa-cogs  '))->resolve());
        $this->assertSame('My Label', (new Label('  My Label  '))->resolve());
        $this->assertSame('/admin/config', (new Route('  /admin/config  '))->resolve());
        $this->assertSame('{__API__}', (new Payload('  {__API__}  '))->resolve());
        $this->assertSame('\\PSFS\\base\\dto\\JsonResponse(data={__API__})', (new ApiReturn('  \\PSFS\\base\\dto\\JsonResponse(data={__API__})  '))->resolve());
        $this->assertSame('\\PSFS\\base\\Security', (new VarType('  \\PSFS\\base\\Security  '))->resolve());
    }

    public function testStringBasedAttributesRejectEmptyValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Route('   ');
    }

    public function testDefaultValueAllowsEmptyButTrimsWhitespace(): void
    {
        $this->assertSame('', (new DefaultValue('   '))->resolve());
        $this->assertSame('phpName', (new DefaultValue('  phpName  '))->resolve());
    }

    public function testValuesNormalizesStringAndArrayEntries(): void
    {
        $this->assertSame('a|b|c', (new Values('  a|b|c  '))->resolve());
        $this->assertSame(['A', 'B', 1], (new Values([' A ', 'B ', 1]))->resolve());
    }

    public function testHttpMethodNormalizesAndValidatesAllowedMethods(): void
    {
        $this->assertSame('GET', (new HttpMethod(' get '))->resolve());
        $this->assertSame('ALL', (new HttpMethod())->resolve());
    }

    public function testHttpMethodRejectsUnknownMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HttpMethod('TRACE_CUSTOM');
    }

    public function testBooleanAttributesReturnConfiguredValue(): void
    {
        $this->assertTrue((new Required(true))->resolve());
        $this->assertFalse((new Visible(false))->resolve());
        $this->assertTrue((new Cacheable(true))->resolve());
        $this->assertTrue((new ApiDeprecated(true))->resolve());
    }
}
