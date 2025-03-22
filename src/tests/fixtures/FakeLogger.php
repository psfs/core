<?php

namespace PSFS\base;

class Logger
{
    public static array $logs = [];

    public static function log($msg, $level = 0): void
    {
        self::$logs[] = $msg;
    }
}
