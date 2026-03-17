<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\Request;
use PSFS\base\types\helpers\RequestHelper;
use PSFS\base\types\helpers\ResponseHelper;

class RequestResponseSecurityContractTest extends TestCase
{
    public function testNormalizeOrigin(): void
    {
        $this->assertEquals('https://api.example.com', RequestHelper::normalizeOrigin('https://api.example.com/v1/users'));
        $this->assertEquals('http://localhost:8011', RequestHelper::normalizeOrigin('http://localhost:8011/path?q=1'));
        $this->assertNull(RequestHelper::normalizeOrigin('javascript:alert(1)'));
        $this->assertNull(RequestHelper::normalizeOrigin(null));
    }

    public function testResolveAllowedOrigin(): void
    {
        $origin = 'https://api.example.com';

        $this->assertEquals($origin, RequestHelper::resolveAllowedOrigin($origin, 'https://api.example.com,https://admin.example.com'));
        $this->assertEquals($origin, RequestHelper::resolveAllowedOrigin($origin, 'https://*.example.com'));
        $this->assertEquals('*', RequestHelper::resolveAllowedOrigin($origin, '*'));
        $this->assertNull(RequestHelper::resolveAllowedOrigin($origin, 'https://other.example.com'));
        $this->assertNull(RequestHelper::resolveAllowedOrigin('', 'https://api.example.com'));
    }

    public function testNormalizeCookieDomain(): void
    {
        $this->assertEquals('example.com', ResponseHelper::normalizeCookieDomain('https://example.com:8080'));
        $this->assertEquals('sub.example.com', ResponseHelper::normalizeCookieDomain('sub.example.com'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain('localhost'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain('127.0.0.1'));
        $this->assertNull(ResponseHelper::normalizeCookieDomain(null));
    }

    public function testNormalizeSameSite(): void
    {
        $this->assertEquals('Lax', ResponseHelper::normalizeSameSite(''));
        $this->assertEquals('Lax', ResponseHelper::normalizeSameSite(null));
        $this->assertEquals('Strict', ResponseHelper::normalizeSameSite('strict'));
        $this->assertEquals('None', ResponseHelper::normalizeSameSite('none'));
    }

    public function testIsSecureRequestDetectsForwardedProto(): void
    {
        $request = Request::getInstance();
        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $this->assertTrue(ResponseHelper::isSecureRequest());

        $request->setServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTPS' => '',
            'REQUEST_SCHEME' => 'http',
        ]);
        $this->assertFalse(ResponseHelper::isSecureRequest());
    }
}
