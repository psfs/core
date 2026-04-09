<?php

namespace PSFS\tests\base\type\helper;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\attributes\Injectable;

class InjectableAttributeTest extends TestCase
{
    public function testInjectableAttributeNormalizesClassAndDefaultsFlags(): void
    {
        $injectable = new Injectable(class: 'PSFS\\base\\Security');
        $resolved = $injectable->resolve();

        $this->assertSame('\\PSFS\\base\\Security', $resolved['class']);
        $this->assertTrue($resolved['singleton']);
        $this->assertTrue($resolved['required']);
    }

    public function testInjectableAttributeRejectsEmptyClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Injectable(class: '');
    }
}
