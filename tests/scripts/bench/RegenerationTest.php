<?php

namespace PSFS\tests\scripts\bench;

use Psfs\Bench\Regeneration;

class RegenerationTest extends BenchTestCase
{
    public function testTheReportHasOneRowPerMeasureWithItsPhases(): void
    {
        $report = Regeneration::report([$this->measure(100, 'regen'), $this->measure(30, 'repeat')]);

        $lines = explode("\n", trim($report));
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('| 100 | regen | 1.5 | 50 | 1215 | abcdef12 |', $lines[2]);
        $this->assertStringContainsString('migration-reverse 680 (+4) x1', $lines[2]);
        $this->assertStringContainsString('| 30 | repeat |', $lines[3]);
    }

    public function testEveryStateIsKnown(): void
    {
        $this->assertSame(['initial', 'regen', 'repeat'], Regeneration::STATES);
    }

    /**
     * @return array<string,mixed>
     */
    private function measure(int $tables, string $state): array
    {
        return [
            'tables' => $tables,
            'state' => $state,
            'wall_ms' => 1500.0,
            'peak_mb' => 50.0,
            'files' => 1215,
            'tree' => 'abcdef1234567890',
            'profile' => [
                'propel-models' => ['ms' => 681.0, 'peak' => 10485760, 'runs' => 1],
                'migration-reverse' => ['ms' => 680.0, 'peak' => 4194304, 'runs' => 1],
            ],
        ];
    }
}
