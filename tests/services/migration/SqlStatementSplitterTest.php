<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\SqlStatementSplitter;

class SqlStatementSplitterTest extends TestCase
{
    public function testSplitIgnoresSemicolonsInsideQuotes(): void
    {
        $splitter = new SqlStatementSplitter();
        $sql = "INSERT INTO test VALUES ('a;b');\nUPDATE test SET value=\"x;y\";\nDELETE FROM test WHERE id=1;";

        $statements = $splitter->split($sql);

        $this->assertCount(3, $statements);
        $this->assertSame("INSERT INTO test VALUES ('a;b')", $statements[0]);
        $this->assertSame('UPDATE test SET value="x;y"', $statements[1]);
        $this->assertSame('DELETE FROM test WHERE id=1', $statements[2]);
    }

    public function testSplitSkipsEmptyStatements(): void
    {
        $splitter = new SqlStatementSplitter();

        $statements = $splitter->split(";  ;SELECT 1;;");

        $this->assertSame(['SELECT 1'], $statements);
    }
}
