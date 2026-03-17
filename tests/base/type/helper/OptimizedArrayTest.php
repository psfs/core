<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\OptimizedArray;

class OptimizedArrayTest extends TestCase
{
    public function testFromArrayIsIterableAndCountable(): void
    {
        $items = ['a' => 10, 'b' => 20, 'c' => 30];
        $optimized = OptimizedArray::fromArray($items);

        $this->assertCount(3, $optimized);
        $this->assertSame($items, $optimized->toArray());
    }

    public function testFromGeneratorSupportsYieldAndCountHint(): void
    {
        $optimized = OptimizedArray::fromGenerator(static function (): \Generator {
            for ($i = 1; $i <= 4; $i++) {
                yield $i;
            }
        }, 4);

        $this->assertCount(4, $optimized);
        $this->assertSame([1, 2, 3, 4], array_values(iterator_to_array($optimized)));
    }
}
