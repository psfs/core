<?php

namespace PSFS\base;

/**
 * Class Singleton
 * @package PSFS\base
 */
class Singleton
{
    /**
     * @var Singleton cached reference to singleton instance
     */
    protected static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return $this
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(self::$instance[$class]) || !self::$instance[$class] instanceof $class) {
            self::$instance[$class] = new $class(func_get_args());
        }
        return self::$instance[$class];
    }

    /**
     * is not allowed to call from outside: private!
     *
     */
    private function __construct(){}

    /**
     * prevent the instance from being cloned
     *
     * @return void
     */
    private function __clone(){}

    /**
     * prevent from being unserialized
     *
     * @return void
     */
    private function __wakeup(){}
}