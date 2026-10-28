<?php

namespace PSFS\tests\scripts\bench;

use Psfs\Bench\RuntimeReport;

class RuntimeReportTest extends BenchTestCase
{
    public function testEveryMeasuredCellBecomesARowOfTheTable(): void
    {
        $report = RuntimeReport::fromLines([
            '{"scenario":"page","runtime":"php -S","concurrency":1,"rps":173.6,"p50":5.25,"p95":7.68}',
            '',
            '{"scenario":"api-get","runtime":"swoole x2","concurrency":8,"rps":264.2,"p50":28.7,"p95":40.04}',
        ], '## Title');

        $lines = explode("\n", trim($report));
        $this->assertSame('## Title', $lines[0]);
        $this->assertSame('| page | php -S | 1 | 174 | 5.25 | 7.68 |', $lines[4]);
        $this->assertSame('| api-get | swoole x2 | 8 | 264 | 28.70 | 40.04 |', $lines[5]);
        $this->assertCount(6, $lines);
    }

    public function testACellThatFailedIsReportedWithItsReason(): void
    {
        $report = RuntimeReport::fromLines(['{"scenario":"page","runtime":"nginx + php-fpm","concurrency":32,"error":"HTTP 0"}'], 'T');

        $this->assertStringContainsString('| page | nginx + php-fpm | 32 | failed: HTTP 0 | | |', $report);
    }
}
