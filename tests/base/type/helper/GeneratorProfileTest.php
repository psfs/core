<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\GeneratorProfile;

class GeneratorProfileTest extends TestCase
{
    protected function setUp(): void
    {
        GeneratorProfile::reset();
    }

    protected function tearDown(): void
    {
        putenv('PSFS_GENERATOR_PROFILE');
        GeneratorProfile::reset();
    }

    public function testItMeasuresNothingUnlessItIsEnabled(): void
    {
        GeneratorProfile::start('models');
        GeneratorProfile::stop('models');

        $this->assertFalse(GeneratorProfile::isEnabled());
        $this->assertSame([], GeneratorProfile::report());
        $this->assertSame('', GeneratorProfile::summary());
    }

    public function testItAccumulatesTimeAndRunsPerPhase(): void
    {
        putenv('PSFS_GENERATOR_PROFILE=true');

        GeneratorProfile::start('models');
        usleep(2000);
        GeneratorProfile::stop('models');
        GeneratorProfile::start('models');
        $held = str_repeat('x', 2 * 1048576);
        GeneratorProfile::stop('models');
        GeneratorProfile::start('api');
        GeneratorProfile::stop('api');

        $report = GeneratorProfile::report();
        $this->assertSame(['models', 'api'], array_keys($report));
        $this->assertSame(2, $report['models']['runs']);
        $this->assertGreaterThanOrEqual(2.0, $report['models']['ms']);
        $this->assertGreaterThanOrEqual(strlen($held), $report['models']['peak']);
        $this->assertSame(1, $report['api']['runs']);
        $this->assertStringContainsString('models: ', GeneratorProfile::summary());
        $this->assertStringContainsString('2 run(s)', GeneratorProfile::summary());
    }

    public function testStoppingAPhaseThatWasNeverStartedChangesNothing(): void
    {
        putenv('PSFS_GENERATOR_PROFILE=true');

        GeneratorProfile::stop('never-started');

        $this->assertSame([], GeneratorProfile::report());
    }

    public function testResetForgetsThePhases(): void
    {
        putenv('PSFS_GENERATOR_PROFILE=true');
        GeneratorProfile::start('models');
        GeneratorProfile::stop('models');

        GeneratorProfile::reset();

        $this->assertSame([], GeneratorProfile::report());
    }

    public function testLoggingTheSummaryNeedsAnEnabledProfileWithMeasures(): void
    {
        GeneratorProfile::logSummary();
        putenv('PSFS_GENERATOR_PROFILE=true');
        GeneratorProfile::logSummary();
        GeneratorProfile::start('models');
        GeneratorProfile::stop('models');
        GeneratorProfile::logSummary();

        $this->assertStringContainsString('models: ', GeneratorProfile::summary());
    }
}
