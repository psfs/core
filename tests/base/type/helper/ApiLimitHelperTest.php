<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\ApiLimitHelper;
use PSFS\base\types\helpers\CompatHelper;

class ApiLimitHelperTest extends TestCase
{
    private const ENV = ['PSFS_API_MAX_LIMIT', 'PSFS_API_LIMIT_UNBOUNDED'];

    protected function setUp(): void
    {
        $this->clearEnv();
        CompatHelper::reset();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
        CompatHelper::reset();
    }

    private function clearEnv(): void
    {
        foreach (self::ENV as $name) {
            putenv($name);
        }
    }

    public function testLimitsUpToTheMaximumAreUntouched(): void
    {
        self::assertSame([100, false], ApiLimitHelper::resolve(100));
        self::assertSame([1000, false], ApiLimitHelper::resolve(1000));
        self::assertSame([1, false], ApiLimitHelper::resolve(1));
    }

    public function testLimitsAboveTheMaximumAreCapped(): void
    {
        self::assertSame([1000, true], ApiLimitHelper::resolve(1001));
        self::assertSame([1000, true], ApiLimitHelper::resolve(100000));
    }

    public function testTheWholeTableRequestIsCappedToo(): void
    {
        self::assertSame([ApiLimitHelper::DEFAULT_MAX, true], ApiLimitHelper::resolve(-1));
    }

    public function testTheMaximumIsConfigurable(): void
    {
        putenv('PSFS_API_MAX_LIMIT=50');
        self::assertSame([50, true], ApiLimitHelper::resolve(100));
        self::assertSame([50, true], ApiLimitHelper::resolve(-1));
        self::assertSame([50, false], ApiLimitHelper::resolve(50));
    }

    public function testNonPositiveMaximumsAreRaisedToOne(): void
    {
        putenv('PSFS_API_MAX_LIMIT=0');
        self::assertSame([1, true], ApiLimitHelper::resolve(5));
    }

    public function testCompatibilitySwitchRestoresUnboundedLists(): void
    {
        putenv('PSFS_API_LIMIT_UNBOUNDED=true');

        self::assertSame([-1, false], ApiLimitHelper::resolve(-1));
        self::assertSame([5000, false], ApiLimitHelper::resolve(5000));
    }
}
