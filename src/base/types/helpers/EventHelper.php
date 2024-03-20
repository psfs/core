<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Logger;

class EventHelper
{
    const EVENT_START_REQUEST = 'psfs_start_request';
    const EVENT_END_REQUEST = 'psfs_end_request';

    protected static array $events = [];

    public static function handleEvents(string $eventName, mixed $context = null): void
    {
        if(array_key_exists($eventName, self::$events)) {
            foreach(self::$events[$eventName] as $eventClass) {
                $return = (new $eventClass)($context);
                Logger::log("$eventClass event handled with return $return");
            }
        }
    }

    public static function addEvent(string $eventName, string $eventClass): void
    {
        if(!array_key_exists($eventName, self::$events)) {
            self::$events[$eventName] = [];
        }
        self::$events[$eventName][] = $eventClass;
    }
}
