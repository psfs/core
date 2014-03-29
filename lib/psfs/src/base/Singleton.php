<?php

namespace PSFS\base;

/**
 * Class Singleton
 * @package PSFS\base
 */
class Singleton
{
    /**
     * @var cached reference to singleton instance
     */
    protected static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return self
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!self::$instance instanceof $class) {
            self::$instance = new $class;
        }
        return self::$instance;
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