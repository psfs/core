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

    public function testFromIterableSupportsSelfIteratorAndTraversable(): void
    {
        $base = OptimizedArray::fromArray(['x' => 1, 'y' => 2]);
        $fromSelf = OptimizedArray::fromIterable($base, 99);
        $fromIterator = OptimizedArray::fromIterable(new \ArrayIterator(['i' => 7]));
        $fromTraversable = OptimizedArray::fromIterable((static function (): \Generator {
            yield 'g' => 11;
        })());

        $this->assertCount(99, $fromSelf);
        $this->assertSame(['x' => 1, 'y' => 2], $fromSelf->toArray());
        $this->assertSame(['i' => 7], $fromIterator->toArray());
        $this->assertSame(['g' => 11], $fromTraversable->toArray());
    }

    public function testFromGeneratorThrowsWhenFactoryReturnsNonIterable(): void
    {
        $optimized = OptimizedArray::fromGenerator(static fn() => 123);

        $this->expectException(\UnexpectedValueException::class);
        iterator_to_array($optimized);
    }

    public function testGetIteratorThrowsWhenFactoryReturnsNonIterable(): void
    {
        $optimized = new OptimizedArray(static fn() => 'not-iterable');

        $this->expectException(\UnexpectedValueException::class);
        iterator_to_array($optimized->getIterator());
    }

    public function testCountWithoutHintCachesComputedValue(): void
    {
        $calls = 0;
        $optimized = new OptimizedArray(static function () use (&$calls): \Generator {
            $calls++;
            yield 1;
            yield 2;
        });

        $this->assertSame(2, $optimized->count());
        $this->assertSame(2, $optimized->count());
        $this->assertSame(1, $calls);
    }
}
