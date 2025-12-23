<?php

namespace PSFS\tests\fixtures;

class FakeLogger
{
    public static array $logs = [];

    public static function log($msg, $level = 0): void
    {
        self::$logs[] = $msg;
    }
}
