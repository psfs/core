<?php

namespace PSFS\base;

class SingletonRegistry
{
    const CONTEXT_SESSION = 'PSFS_CONTEXT_SESSION';
    private static $instances = [];

    public static function register($instance)
    {
        $currentContext = $_SERVER[self::CONTEXT_SESSION] ?? self::CONTEXT_SESSION;
        if ($instance) {
            if (!isset(self::$instances[$currentContext])) {
                self::$instances[$currentContext] = [];
            }
            $class = get_class($instance);
            self::$instances[$currentContext][$class] = $instance;
        }
    }

    public static function get($class)
    {
        $currentContext = $_SERVER[self::CONTEXT_SESSION] ?? self::CONTEXT_SESSION;
        return self::$instances[$currentContext][$class] ?? null;
    }

    public static function clear()
    {
        $currentContext = $_SERVER[self::CONTEXT_SESSION] ?? self::CONTEXT_SESSION;
        unset(self::$instances[$currentContext]);
    }

    public static function drop($class)
    {
        $currentContext = $_SERVER[self::CONTEXT_SESSION] ?? self::CONTEXT_SESSION;
        unset(self::$instances[$currentContext][$class]);
    }
}