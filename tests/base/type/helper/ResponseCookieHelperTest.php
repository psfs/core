<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\ResponseCookieHelper;

class ResponseCookieHelperTest extends TestCase
{
    public function testParseSetCookieHeaderValueParsesFlagsAndAttributes(): void
    {
        $cookie = ResponseCookieHelper::parseSetCookieHeaderValue(
            'sid=abc; Path=/api; Domain=example.com; Secure; HttpOnly; SameSite=strict; Max-Age=120'
        );

        $this->assertIsArray($cookie);
        $this->assertSame('sid', $cookie['name']);
        $this->assertSame('abc', $cookie['value']);
        $this->assertSame('/api', $cookie['path']);
        $this->assertSame('example.com', $cookie['domain']);
        $this->assertTrue($cookie['secure']);
        $this->assertTrue($cookie['httponly']);
        $this->assertSame('Strict', $cookie['samesite']);
        $this->assertSame(120, $cookie['max_age']);
    }

    public function testBuildSessionCookieHeaderValueIncludesSecurityAndMaxAgeWhenLifetimeIsSet(): void
    {
        $line = ResponseCookieHelper::buildSessionCookieHeaderValue('PSFSSESSID', 'token-1', [
            'lifetime' => 60,
            'path' => '/',
            'domain' => 'example.com',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'none',
        ]);

        $this->assertStringStartsWith('PSFSSESSID=token-1', $line);
        $this->assertStringContainsString('Domain=example.com', $line);
        $this->assertStringContainsString('Secure', $line);
        $this->assertStringContainsString('HttpOnly', $line);
        $this->assertStringContainsString('SameSite=None', $line);
        $this->assertStringContainsString('Max-Age=60', $line);
    }
}
