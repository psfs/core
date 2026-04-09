<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\RequestPayloadHelper;

class RequestPayloadHelperTest extends TestCase
{
    public function testParseHeadersExtractsHttpServerHeaders(): void
    {
        $headers = RequestPayloadHelper::parseHeaders([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_API_TOKEN' => 'abc',
            'SERVER_NAME' => 'localhost',
        ]);

        $this->assertSame('application/json', $headers['ACCEPT'] ?? null);
        $this->assertSame('abc', $headers['X_API_TOKEN'] ?? null);
        $this->assertArrayNotHasKey('SERVER_NAME', $headers);
    }

    public function testHydratePayloadAndDecodeRawBody(): void
    {
        $bags = RequestPayloadHelper::hydratePayloadBags(
            ['a' => '1'],
            ['file' => ['name' => 'a.txt']],
            ['b' => '2'],
            ['c' => '3']
        );

        $this->assertSame(['a' => '1'], $bags['cookies']);
        $this->assertSame(['file' => ['name' => 'a.txt']], $bags['upload']);
        $this->assertSame(['b' => '2'], $bags['data']);
        $this->assertSame(['c' => '3'], $bags['query']);
        $this->assertSame(['ok' => true], RequestPayloadHelper::decodeRawBody('{"ok":true}'));
        $this->assertSame([], RequestPayloadHelper::decodeRawBody('invalid'));
    }
}
