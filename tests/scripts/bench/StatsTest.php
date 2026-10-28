<?php

namespace PSFS\tests\scripts\bench;

use Psfs\Bench\Stats;

class StatsTest extends BenchTestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function testPercentilesUseTheNearestRank(): void
    {
        $values = range(1, 100);

        self::assertSame(50.0, Stats::percentile($values, 50));
        self::assertSame(95.0, Stats::percentile($values, 95));
        self::assertSame(100.0, Stats::percentile($values, 100));
        self::assertSame(1.0, Stats::percentile($values, 1));
    }

    public function testPercentileOfASingleValueIsThatValue(): void
    {
        self::assertSame(7.5, Stats::percentile([7.5], 50));
        self::assertSame(7.5, Stats::percentile([7.5], 95));
    }

    public function testMedianIgnoresTheOrderOfTheInput(): void
    {
        self::assertSame(3, Stats::median([5, 1, 3]));
        self::assertSame(2.5, Stats::median([9.0, 2.5, 1.0]));
    }

    public function testServerLogIsSummarisedPerRequest(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'bench-stats');
        file_put_contents($this->file, implode('', [
            "2097152\t250\t3.000\t1\n",
            "1048576\t260\t1.000\t1\n",
            "3145728\t255\t2.000\t1\n",
        ]));

        $stats = Stats::fromServerLog($this->file);

        self::assertSame(2.0, $stats['php_p50']);
        self::assertSame(2.0, $stats['peak_mb']);
        self::assertSame(255, $stats['files']);
        self::assertTrue($stats['opcache']);
    }

    public function testTheOpcacheStateIsTheOneOfTheLastRequest(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'bench-stats');
        file_put_contents($this->file, "1\t1\t1.0\t1\n1\t1\t1.0\t0\n");

        self::assertFalse(Stats::fromServerLog($this->file)['opcache']);
    }

    public function testAnEmptyServerLogIsAnError(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'bench-stats');
        file_put_contents($this->file, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('reported no statistics');

        Stats::fromServerLog($this->file);
    }
}
