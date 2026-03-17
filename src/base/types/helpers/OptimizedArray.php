<?php

namespace PSFS\base\types\helpers;

use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use Traversable;
use UnexpectedValueException;

/**
 * Experimental iterable wrapper focused on lazy iteration with optional cached count.
 */
class OptimizedArray implements IteratorAggregate, Countable
{
    /** @var callable */
    private $iterableFactory;
    private ?int $countHint;

    /**
     * @param callable $iterableFactory Factory returning an iterable for each iteration.
     * @param int|null $countHint Optional count hint to avoid traversing.
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
     * Creates an OptimizedArray from any iterable.
     * Iterator instances are materialized once to preserve re-iterability.
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
     * @param callable $generatorFactory Callable returning iterable values.
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
