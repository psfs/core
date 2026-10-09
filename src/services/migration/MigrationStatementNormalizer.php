<?php

namespace PSFS\services\migration;

/**
 * Converts Propel's nested migration payloads into individual SQL statements.
 */
final class MigrationStatementNormalizer
{
    public function __construct(private readonly SqlStatementSplitter $splitter)
    {
    }

    /**
     * @param array<string, mixed> $migrationSql
     * @return list<string>
     */
    public function normalize(array $migrationSql): array
    {
        return array_values(iterator_to_array($this->iterate($migrationSql), false));
    }

    /**
     * @param array<string, mixed> $migrationSql
     * @return \Generator<int, string>
     */
    private function iterate(array $migrationSql): \Generator
    {
        foreach ($migrationSql as $value) {
            foreach ($this->stringsFrom($value) as $sql) {
                yield from $this->splitAndNormalize($sql);
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function stringsFrom(mixed $value): iterable
    {
        if (is_string($value)) {
            yield $value;
            return;
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                if (is_string($nestedValue)) {
                    yield $nestedValue;
                }
            }
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function splitAndNormalize(string $sql): \Generator
    {
        foreach ($this->splitter->split($sql) as $statement) {
            $statement = trim($statement);
            if ('' !== $statement) {
                yield $statement;
            }
        }
    }
}
