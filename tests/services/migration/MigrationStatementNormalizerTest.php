<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\MigrationStatementNormalizer;
use PSFS\services\migration\SqlStatementSplitter;

class MigrationStatementNormalizerTest extends TestCase
{
    public function testNormalizesNestedAndFlatSqlStatementsAndSkipsUnsupportedValues(): void
    {
        $normalizer = new MigrationStatementNormalizer(new SqlStatementSplitter());

        $statements = $normalizer->normalize([
            'main' => [' SELECT 1; ', 42, 'SELECT 2; SELECT 3;'],
            'audit' => ' DELETE FROM audit; ',
            'ignored' => null,
        ]);

        $this->assertSame(['SELECT 1', 'SELECT 2', 'SELECT 3', 'DELETE FROM audit'], $statements);
    }

    public function testReturnsEmptyListWhenNoStringStatementsAreProvided(): void
    {
        $normalizer = new MigrationStatementNormalizer(new SqlStatementSplitter());

        $this->assertSame([], $normalizer->normalize(['main' => [null, 7], 'audit' => null]));
    }
}
