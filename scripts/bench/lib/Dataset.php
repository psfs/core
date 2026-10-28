<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The rows of the API table (and their translations) in the sandbox database
 */
final class Dataset
{
    private readonly string $schema;

    public function __construct(private readonly \PDO $pdo, string $database)
    {
        $this->schema = '`' . $database . '`';
    }

    /**
     * Grows or shrinks CLIENT_TEST to the requested number of rows
     */
    public function useRows(int $rows): void
    {
        $current = $this->lastId();
        if ($current === $rows) {
            return;
        }
        if ($current > $rows) {
            $this->pdo->exec("DELETE FROM {$this->schema}.CLIENT_TEST_i18n WHERE ID > $rows");
            $this->pdo->exec("DELETE FROM {$this->schema}.CLIENT_TEST WHERE ID > $rows");
            return;
        }
        $this->insertRows($current, $rows);
        $this->insertTranslations($current);
        // ANALYZE returns a result set: it has to be consumed or the connection stays busy
        $this->pdo->query("ANALYZE TABLE {$this->schema}.CLIENT_TEST, {$this->schema}.CLIENT_TEST_i18n")->fetchAll();
    }

    private function lastId(): int
    {
        $statement = $this->pdo->query("SELECT COALESCE(MAX(ID), 0) FROM {$this->schema}.CLIENT_TEST");
        return (int)$statement->fetchAll(\PDO::FETCH_COLUMN)[0];
    }

    private function insertRows(int $current, int $rows): void
    {
        $this->pdo->exec('SET SESSION cte_max_recursion_depth = ' . ($rows + 10));
        $this->pdo->exec("INSERT INTO {$this->schema}.CLIENT_TEST (NUMBER, SUMMARY, TYPE, CHECKER, ID_RELATED, created_at, updated_at)
            WITH RECURSIVE seq(n) AS (SELECT " . ($current + 1) . " UNION ALL SELECT n + 1 FROM seq WHERE n < $rows)
            SELECT n, CONCAT('Bench row ', n), 1, n % 2, 1, NOW(), NOW() FROM seq");
    }

    /**
     * Gives every new row a translation shaped like the first existing one
     */
    private function insertTranslations(int $current): void
    {
        $statement = $this->pdo->query("SELECT * FROM {$this->schema}.CLIENT_TEST_i18n ORDER BY ID LIMIT 1");
        $template = $statement->fetchAll(\PDO::FETCH_ASSOC)[0] ?? null;
        if (null === $template) {
            return;
        }
        $columns = array_keys($template);
        $this->pdo->exec(sprintf(
            "INSERT INTO {$this->schema}.CLIENT_TEST_i18n (%s) SELECT %s FROM {$this->schema}.CLIENT_TEST t WHERE t.ID > %d",
            implode(',', array_map(static fn(string $column): string => "`$column`", $columns)),
            implode(',', array_map(fn(string $column): string => $this->value($column, $template[$column]), $columns)),
            $current
        ));
    }

    private function value(string $column, int|float|string|null $templateValue): string
    {
        if ('ID' === $column) {
            return 't.ID';
        }
        return null === $templateValue ? 'NULL' : $this->pdo->quote((string)$templateValue);
    }
}
