<?php

namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\SensitiveDataHelper;

class SensitiveDataHelperTest extends TestCase
{
    public function testRedactMasksSensitiveKeysRecursively(): void
    {
        $input = [
            'password' => 'secret',
            'nested' => [
                'Authorization' => 'Bearer abc',
                'safe' => 'value',
                'api_key' => 'key123',
            ],
            'token_value' => 'jwt',
        ];

        $redacted = SensitiveDataHelper::redact($input);

        $this->assertSame('***', $redacted['password']);
        $this->assertSame('***', $redacted['nested']['Authorization']);
        $this->assertSame('***', $redacted['nested']['api_key']);
        $this->assertSame('***', $redacted['token_value']);
        $this->assertSame('value', $redacted['nested']['safe']);
    }

    public function testRedactReturnsScalarAsIs(): void
    {
        $this->assertSame('plain', SensitiveDataHelper::redact('plain'));
        $this->assertSame(10, SensitiveDataHelper::redact(10));
        $this->assertNull(SensitiveDataHelper::redact(null));
    }
}
