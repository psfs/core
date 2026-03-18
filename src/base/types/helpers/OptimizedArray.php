<?php

namespace PSFS\base\types\helpers;

use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use Traversable;
use UnexpectedValueException;


class OptimizedArray implements IteratorAggregate, Countable
{

    private $iterableFactory;
    private ?int $countHint;

    /**
     * @param callable $iterableFactory
     * @param int|null $countHint
     */
    public function __construct(callable $iterableFactory, ?int $countHint = null)
    {
        $this->iterableFactory = $iterableFactory;
        $this->countHint = $countHint;
    }

    public static function fromArray(array $items): self
    {
        return new self(
            static function () use ($items): Generator {
                foreach ($items as $key => $item) {
                    yield $key => $item;
                }
            },
            count($items)
        );
    }

    /**
     *
     * @param iterable $items
     * @param int|null $countHint
     * @return self
     */
    public static function fromIterable(iterable $items, ?int $countHint = null): self
    {
        if (is_array($items)) {
            return self::fromArray($items);
        }
        if ($items instanceof self) {
            return new self($items->iterableFactory, $countHint ?? $items->countHint);
        }
        if ($items instanceof Iterator) {
            return self::fromArray(iterator_to_array($items, true));
        }
        return new self(
            static function () use ($items): Generator {
                foreach ($items as $key => $item) {
                    yield $key => $item;
                }
            },
            $countHint
        );
    }

    /**
     * @param callable $generatorFactory
     * @param int|null $countHint
     * @return self
     */
    public static function fromGenerator(callable $generatorFactory, ?int $countHint = null): self
    {
        return new self(
            static function () use ($generatorFactory): Generator {
                $iterable = $generatorFactory();
                if (!is_iterable($iterable)) {
                    throw new UnexpectedValueException('Generator factory must return iterable');
                }
                foreach ($iterable as $key => $item) {
                    yield $key => $item;
                }
            },
            $countHint
        );
    }

    public function getIterator(): Traversable
    {
        $iterable = ($this->iterableFactory)();
        if (!is_iterable($iterable)) {
            throw new UnexpectedValueException('OptimizedArray factory must return iterable');
        }
        foreach ($iterable as $key => $item) {
            yield $key => $item;
        }
    }

    public function count(): int
    {
        if (null !== $this->countHint) {
            return $this->countHint;
        }
        $count = 0;
        foreach ($this as $_) {
            $count++;
        }
        $this->countHint = $count;
        return $count;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator(), true);
    }
}
