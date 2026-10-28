<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\EventHelper;

class EventHelperFailureTest extends TestCase
{
    protected function tearDown(): void
    {
        EventHelper::clear();
    }

    public function testAFailingListenerDoesNotStopTheOthers(): void
    {
        $failing = new class {
            public function __invoke($context)
            {
                throw new \RuntimeException('listener failed');
            }
        };
        $ran = new class {
            public static int $calls = 0;

            public function __invoke($context)
            {
                self::$calls++;
                return 'ok';
            }
        };
        $ran::$calls = 0;
        EventHelper::addEvent('bench.event', get_class($failing));
        EventHelper::addEvent('bench.event', get_class($ran));

        EventHelper::handleEvents('bench.event', ['payload']);

        self::assertSame(1, $ran::$calls);
    }

    public function testClearWithoutNameRemovesEveryEvent(): void
    {
        $listener = new class {
            public static int $calls = 0;

            public function __invoke($context)
            {
                self::$calls++;
            }
        };
        $listener::$calls = 0;
        EventHelper::addEvent('first', get_class($listener));
        EventHelper::addEvent('second', get_class($listener));

        EventHelper::clear('first');
        EventHelper::handleEvents('first');
        EventHelper::handleEvents('second');
        self::assertSame(1, $listener::$calls);

        EventHelper::clear();
        EventHelper::handleEvents('second');
        self::assertSame(1, $listener::$calls);
    }

    public function testRegisteringTheSameListenerTwiceRunsItOnce(): void
    {
        $listener = new class {
            public static int $calls = 0;

            public function __invoke($context)
            {
                self::$calls++;
            }
        };
        $listener::$calls = 0;
        EventHelper::addEvent('once', get_class($listener));
        EventHelper::addEvent('once', get_class($listener));

        EventHelper::handleEvents('once');

        self::assertSame(1, $listener::$calls);
    }
}
