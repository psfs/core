<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The cells of the benchmark: the product of every dimension, per scenario.
 * Only the API scenarios depend on the table size, so the others measure a single row count.
 */
final class Matrix
{
    public function __construct(private readonly Options $options)
    {
    }

    public function total(): int
    {
        $total = 0;
        foreach ($this->options->list('scenarios') as $scenario) {
            $total += array_product(array_map('count', $this->dimensions($scenario)));
        }
        return $total;
    }

    /**
     * @return \Generator<int,array{scenario:string,modules:int,rows:int,debug:bool,redis:string,opcache:string}>
     */
    public function cells(): \Generator
    {
        foreach ($this->options->list('scenarios') as $scenario) {
            foreach (self::expand($this->dimensions($scenario), ['scenario' => $scenario]) as $cell) {
                yield self::typed($cell);
            }
        }
    }

    /**
     * Dimensions in loop order: the module count changes the project, so it is the slowest one
     *
     * @return array<string,string[]>
     */
    private function dimensions(string $scenario): array
    {
        $rows = $this->options->list('rows');
        return [
            'modules' => $this->options->list('modules'),
            'rows' => Scenarios::isApi($scenario) ? $rows : array_slice($rows, 0, 1),
            'debug' => $this->options->list('debug'),
            'redis' => $this->options->list('redis'),
            'opcache' => $this->options->list('opcache'),
        ];
    }

    /**
     * @param array<string,string[]> $dimensions
     * @param array<string,string> $current
     * @return \Generator<int,array<string,string>>
     */
    private static function expand(array $dimensions, array $current): \Generator
    {
        if ([] === $dimensions) {
            yield $current;
            return;
        }
        $key = array_key_first($dimensions);
        $rest = array_slice($dimensions, 1, null, true);
        foreach ($dimensions[$key] as $value) {
            yield from self::expand($rest, $current + [$key => $value]);
        }
    }

    /**
     * @param array<string,string> $cell
     * @return array{scenario:string,modules:int,rows:int,debug:bool,redis:string,opcache:string}
     */
    private static function typed(array $cell): array
    {
        return [
            'scenario' => $cell['scenario'],
            'modules' => (int)$cell['modules'],
            'rows' => (int)$cell['rows'],
            'debug' => 'true' === $cell['debug'],
            'redis' => $cell['redis'],
            'opcache' => $cell['opcache'],
        ];
    }
}
