<?php

namespace PSFS\tests\scripts\bench;

use Psfs\Bench\CellRunner;
use Psfs\Bench\Options;
use Psfs\Bench\Reporter;

class ReporterTest extends BenchTestCase
{
    private string $json = '';

    protected function tearDown(): void
    {
        if ('' !== $this->json && file_exists($this->json)) {
            unlink($this->json);
        }
    }

    private function sampleResult(array $overrides = []): array
    {
        return $overrides + [
            'scenario' => 'api-get', 'modules' => 2, 'rows' => 1000, 'opcache' => 'on', 'debug' => false, 'redis' => 'off',
            'cold' => 40.0, 'p50' => 2.0, 'p95' => 3.0, 'php_p50' => 1.9, 'peak_mb' => 0.8, 'files' => 238,
            'redis_conn' => 0.0, 'redis_cmd' => 0.0, 'db_conn' => 1.0, 'db_q' => 4.0,
        ];
    }

    private function render(array $arguments, array $results): string
    {
        ob_start();
        (new Reporter(Options::fromArguments($arguments)))->print($results);
        return (string)ob_get_clean();
    }

    public function testTheTableHasAHeaderRowsAndOneEstimateColumnPerRoundTripTime(): void
    {
        $output = $this->render(['--rtt=1,2.5'], [$this->sampleResult()]);

        self::assertStringContainsString('| scenario | mods | rows | opcache | debug | redis | cold ms | p50 | p95 |', $output);
        self::assertStringContainsString('est@1 | est@2.5 |', $output);
        self::assertStringContainsString('| api-get | 2 | 1000 | on | false | off | 40.0 | 2.00 | 3.00 |', $output);
    }

    public function testTheEstimateCountsEveryRoundTrip(): void
    {
        // 4 queries + 0 commands + 2 x (1 connection) = 6 round trips of 5 ms on top of the 2 ms
        $output = $this->render(['--rtt=5'], [$this->sampleResult()]);

        self::assertStringContainsString('| 32.0 |', $output);
    }

    public function testPagesDoNotShowARowCount(): void
    {
        $output = $this->render([], [$this->sampleResult(['scenario' => 'page'])]);

        self::assertStringContainsString('| page | 2 | - | on |', $output);
    }

    public function testFailedCellsAreReportedInTheTable(): void
    {
        $output = $this->render([], [['scenario' => 'page', 'modules' => 2, 'rows' => 1000, 'opcache' => 'off', 'debug' => true, 'redis' => 'on', 'error' => 'boom']]);

        self::assertStringContainsString('| page | 2 | 1000 | off | true | on | FAILED: boom |', $output);
    }

    public function testAWarningMarksTheRowAndAddsAFootnote(): void
    {
        $output = $this->render([], [$this->sampleResult(['warning' => 'opcache state differs from the requested one'])]);

        self::assertStringContainsString('| on! |', $output);
        self::assertStringContainsString('do not trust that row', $output);
    }

    public function testRepeatedRunsShowTheP50Range(): void
    {
        $output = $this->render(['--repeat=3'], [$this->sampleResult(['p50_min' => 1.8, 'p50_max' => 2.4])]);

        self::assertStringContainsString('| p50 | p50 range |', $output);
        self::assertStringContainsString('| 1.80-2.40 |', $output);
        self::assertStringContainsString('median of 3 runs', $output);
    }

    public function testResultsCanBeWrittenAsJson(): void
    {
        $this->json = tempnam(sys_get_temp_dir(), 'bench-json');
        $this->render(['--json=' . $this->json], [$this->sampleResult()]);

        $decoded = json_decode((string)file_get_contents($this->json), true);
        self::assertSame(PHP_VERSION, $decoded['php']);
        self::assertEquals(2.0, $decoded['results'][0]['p50'], 'json_encode drops the zero fraction of 2.0');
        self::assertSame($this->json, $decoded['options']['json']);
    }

    public function testMergingRepeatedRunsKeepsTheMedianAndTheRange(): void
    {
        $merge = new \ReflectionMethod(CellRunner::class, 'merge');
        $runs = [
            $this->sampleResult(['p50' => 3.0, 'db_q' => 4.0, 'opcache_active' => true]),
            $this->sampleResult(['p50' => 1.0, 'db_q' => 5.0, 'opcache_active' => true]),
            $this->sampleResult(['p50' => 2.0, 'db_q' => 4.0, 'opcache_active' => true, 'warning' => 'opcache state differs from the requested one']),
        ];

        $merged = $merge->invoke(null, $runs);

        self::assertSame(2.0, $merged['p50']);
        self::assertSame(1.0, $merged['p50_min']);
        self::assertSame(3.0, $merged['p50_max']);
        self::assertSame(4.0, $merged['db_q']);
        self::assertTrue($merged['opcache_active']);
        self::assertSame('opcache state differs from the requested one', $merged['warning']);
    }
}
